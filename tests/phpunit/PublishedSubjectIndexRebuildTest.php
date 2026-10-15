<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\Installer\DatabaseUpdater;
use ProfessionalWiki\NeoWiki\Application\NullRevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Maintenance\RebuildPublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndexRebuilder;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Needs the database for the tables it rebuilds and the pages it reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndexRebuilder
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\RebuildPublishedSubjectIndex
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onLoadExtensionSchemaUpdates
 * @group Database
 */
class PublishedSubjectIndexRebuildTest extends NeoWikiIntegrationTestCase {

	use ReadsPublishedSubjectIndex;

	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';
	private const string TURING = 's1cturing111111';
	private const string BIRTH = 's1dbirth1111111';

	private const string MONDAY = '20300107090000';
	private const string TUESDAY = '20300108090000';

	public function testRebuildingIndexesAPageWrittenWithoutTheHookUnderItsFullName(): void {
		$pageId = $this->createPageWithoutTellingNeoWiki( 'Help:Ada Lovelace', $this->slotJson( self::ADA, null ) );

		$this->rebuild();

		$this->assertSame( [ self::ADA => 'Help:Ada Lovelace' ], $this->displayNamesOf( $pageId ) );
	}

	/**
	 * The page created after the deleted one takes the walk over pages holding Subjects past the deleted one's id.
	 */
	public function testRebuildingRemovesTheRowsOfAPageDeletedWithoutTheHook(): void {
		$pageId = $this->createPage( 'Ada Lovelace', self::ADA, 'Ada' );
		$this->clearHook( 'PageDeleteComplete' );
		$this->deletePageByName( 'Ada Lovelace' );
		$this->createPage( 'Grace Hopper', self::GRACE, 'Grace' );

		$this->rebuild();

		$this->assertSame( [], $this->displayNamesOf( $pageId ) );
	}

	public function testRebuildingRemovesTheRowsOfAPageThatPublishesNothing(): void {
		$pageId = $this->createPage( 'Ada Lovelace', self::ADA, 'Ada' );

		$this->rebuild( revisionPolicy: FixedRevisionPolicy::publishingNothing() );

		$this->assertSame( [], $this->displayNamesOf( $pageId ) );
	}

	public function testRebuildingRemovesTheRowsOfAPageThatLostItsSubjectSlotWithoutTheHook(): void {
		$pageId = $this->createPage( 'Ada Lovelace', self::ADA, 'Ada' );
		$this->removeSubjectSlotWithoutTellingNeoWiki( 'Ada Lovelace' );

		$this->rebuild();

		$this->assertSame( [], $this->displayNamesOf( $pageId ) );
	}

	public function testRebuildingReachesEveryPageAcrossBatches(): void {
		$adaPageId = $this->createPageWithoutTellingNeoWiki( 'Ada Lovelace', $this->slotJson( self::ADA, 'Ada' ) );
		$gracePageId = $this->createPageWithoutTellingNeoWiki( 'Grace Hopper', $this->slotJson( self::GRACE, 'Grace' ) );
		$turingPageId = $this->createPageWithoutTellingNeoWiki( 'Alan Turing', $this->slotJson( self::TURING, 'Alan' ) );

		$this->rebuild( batchSize: 1 );

		$this->assertSame( [ self::ADA => 'Ada' ], $this->displayNamesOf( $adaPageId ) );
		$this->assertSame( [ self::GRACE => 'Grace' ], $this->displayNamesOf( $gracePageId ) );
		$this->assertSame( [ self::TURING => 'Alan' ], $this->displayNamesOf( $turingPageId ) );
	}

	public function testRebuildingIndexesTheRevisionThePolicyPublishes(): void {
		ConvertibleTimestamp::setFakeTime( self::MONDAY );
		$approved = $this->writeSubjectSlot( 'Ada Lovelace', $this->slotJson( self::ADA, 'Approved name' ) );
		$this->assertNotNull( $approved );
		ConvertibleTimestamp::setFakeTime( self::TUESDAY );
		$this->writeSubjectSlot( 'Ada Lovelace', $this->slotJson( self::ADA, 'Draft name' ) );

		$this->rebuild( revisionPolicy: FixedRevisionPolicy::publishing( $approved ) );

		$this->assertSame( [ self::ADA => 'Approved name' ], $this->displayNamesOf( $approved->getPageId() ) );
		$this->assertSame( [ self::ADA => self::MONDAY ], $this->editTimesOf( $approved->getPageId() ) );
	}

	/**
	 * @dataProvider subjectJsonThatDoesNotDeserializeProvider
	 */
	public function testRebuildingLeavesAPageWhoseSubjectsDoNotDeserializeAsItWas( string $subjectJson ): void {
		$pageId = $this->createPage( 'Ada Lovelace', self::ADA, 'Ada' );
		$this->createPageWithoutTellingNeoWiki( 'Ada Lovelace', $subjectJson );
		$logger = new TestLogger();

		$this->rebuild( logger: $logger );

		$this->assertSame( [ self::ADA => 'Ada' ], $this->displayNamesOf( $pageId ) );
		$this->assertTrue( $logger->hasWarningRecords(), 'the page left as it was should be logged' );
	}

	public static function subjectJsonThatDoesNotDeserializeProvider(): iterable {
		yield 'a Subject that fails validation' => [ TestSubject::jsonThatDoesNotDeserialize( self::ADA ) ];
		yield 'a Subject that is not an object' => [ '{"mainSubject":"' . self::ADA . '","subjects":{"' . self::ADA . '":1}}' ];
	}

	public function testUpdatePhpCreatesTheTablesAndFillsThem(): void {
		$tables = [];
		$postUpdateScripts = [];

		$updater = $this->createMock( DatabaseUpdater::class );
		$updater->method( 'getDB' )->willReturn( $this->getDb() );
		$updater->method( 'addExtensionTable' )->willReturnCallback(
			static function ( string $table ) use ( &$tables ): void {
				$tables[] = $table;
			}
		);
		$updater->method( 'addPostDatabaseUpdateMaintenance' )->willReturnCallback(
			static function ( string $script ) use ( &$postUpdateScripts ): void {
				$postUpdateScripts[] = $script;
			}
		);

		$this->getServiceContainer()->getHookContainer()->run( 'LoadExtensionSchemaUpdates', [ $updater ] );

		$this->assertContains( DatabasePublishedSubjectIndex::SUBJECT_TABLE, $tables );
		$this->assertContains( DatabasePublishedSubjectIndex::RELATION_TABLE, $tables );
		$this->assertContains( RebuildPublishedSubjectIndex::class, $postUpdateScripts );
	}

	/**
	 * Maintenance scripts run as an anonymous user, who on a wiki only members may read can read no Schema.
	 */
	public function testTheUpdateScriptMarksDependentSubjectsOnAWikiOnlyMembersMayRead(): void {
		$this->createSchema( 'Birth', '{"subjectsAreDependent":true,"propertyDefinitions":{}}' );
		$pageId = $this->createPageWithoutTellingNeoWiki( 'Ada Lovelace', $this->slotJsonOfAdaAndHerBirth() );
		$this->setGroupPermissions( '*', 'read', false );

		$this->runUpdateScript();

		$this->assertSame( [ self::ADA => false, self::BIRTH => true ], $this->dependentFlagsOf( $pageId ) );
	}

	public function testTheUpdateScriptFillsTheIndexWithWhatEachPagePublishes(): void {
		$this->clearHook( 'RevisionFromEditComplete' );
		$approved = $this->writeSubjectSlot( 'Ada Lovelace', $this->slotJson( self::ADA, 'Approved name' ) );
		$this->assertNotNull( $approved );
		$this->writeSubjectSlot( 'Ada Lovelace', $this->slotJson( self::ADA, 'Draft name' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );

		$this->runUpdateScript();

		$this->assertSame( [ self::ADA => 'Approved name' ], $this->displayNamesOf( $approved->getPageId() ) );
	}

	private function createPage( string $pageName, string $subjectId, string $label ): int {
		$revision = $this->createPageWithSubjects( $pageName, TestSubject::build( id: $subjectId, label: $label ) );
		$this->assertNotNull( $revision );

		return $revision->getPageId();
	}

	private function slotJson( string $subjectId, ?string $label ): string {
		return SubjectContent::newFromData(
			new PageSubjects( TestSubject::build( id: $subjectId, label: $label ), new SubjectMap() )
		)->getText();
	}

	private function slotJsonOfAdaAndHerBirth(): string {
		return SubjectContent::newFromData( new PageSubjects(
			TestSubject::build(
				id: self::ADA,
				statements: new StatementList( [
					TestStatement::buildRelation( 'Birth', [ TestRelation::build( targetId: self::BIRTH ) ] ),
				] )
			),
			new SubjectMap( TestSubject::build( id: self::BIRTH, label: null, schemaName: new SchemaName( 'Birth' ) ) )
		) )->getText();
	}

	private function rebuild(
		int $batchSize = DatabasePublishedSubjectIndexRebuilder::DEFAULT_BATCH_SIZE,
		RevisionPolicy $revisionPolicy = new NullRevisionPolicy(),
		LoggerInterface $logger = new NullLogger()
	): void {
		$rebuilder = new DatabasePublishedSubjectIndexRebuilder(
			db: $this->getDb(),
			revisionLookup: $this->getServiceContainer()->getRevisionLookup(),
			revisionPolicy: $revisionPolicy,
			titleFormatter: $this->getServiceContainer()->getTitleFormatter(),
			dependentSubjectRules: NeoWikiExtension::getInstance()->newUnrestrictedDependentSubjectRules(),
			logger: $logger,
			batchSize: $batchSize,
		);

		iterator_to_array( $rebuilder->rebuild() );
	}

	private function runUpdateScript(): void {
		$script = new RebuildPublishedSubjectIndex();
		$script->loadWithArgv( [] );

		ob_start();
		try {
			$this->assertTrue( $script->execute() );
		} finally {
			$script->cleanupChanneled();
			ob_end_clean();
		}
	}

}
