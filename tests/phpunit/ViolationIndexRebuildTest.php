<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use JobSpecification;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Registration\ExtensionRegistry;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Validation\SubjectViolation;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\RebuildViolationIndexJob;
use ProfessionalWiki\NeoWiki\Maintenance\RebuildPublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Maintenance\RebuildViolationIndex;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndexRebuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * Needs the integration base and the database for the pages it checks and the table it fills.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndexRebuilder
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\RebuildViolationIndex
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Jobs\RebuildViolationIndexJob
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onLoadExtensionSchemaUpdates
 * @group Database
 */
class ViolationIndexRebuildTest extends NeoWikiIntegrationTestCase {

	private const string ADA = 's1aada111111111';
	private const string GRACE = 's1bgrace1111111';

	protected function setUp(): void {
		parent::setUp();

		$this->createSchema( 'Person', '{"propertyDefinitions":{"Name":{"type":"text","required":true}}}' );
	}

	public function testUpdatePhpCreatesTheTableAndFillsItOnceThePublishedSubjectIndexIsFilled(): void {
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

		$this->assertContains( DatabaseViolationIndex::TABLE, $tables );
		$this->assertSame(
			[ RebuildPublishedSubjectIndex::class, RebuildViolationIndex::class ],
			array_values( array_intersect( $postUpdateScripts, [ RebuildPublishedSubjectIndex::class, RebuildViolationIndex::class ] ) )
		);
	}

	public function testTheUpdateScriptLeavesCheckingThePagesSavedBeforeItToJobs(): void {
		$pageId = $this->createPageWithoutTellingNeoWiki( 'Ada Lovelace', $this->slotJsonOfAPerson( self::ADA ) );

		$this->runUpdateScript();
		$recordedByTheScript = $this->violationCodesOf( $pageId );
		$this->runJobs( [ 'minJobs' => 0 ] );

		$this->assertSame( [ [], [ 'required' ] ], [ $recordedByTheScript, $this->violationCodesOf( $pageId ) ] );
	}

	public function testTheScriptChecksThePagesItselfWhenToldToDoItNow(): void {
		$pageId = $this->createPageWithoutTellingNeoWiki( 'Ada Lovelace', $this->slotJsonOfAPerson( self::ADA ) );

		$this->runUpdateScript( '--now' );

		$this->assertSame( [ 'required' ], $this->violationCodesOf( $pageId ) );
	}

	/**
	 * A new version can tell valid values apart differently without any page changing.
	 */
	public function testTheUpdateRunsOncePerNeoWikiVersion(): void {
		$version = ExtensionRegistry::getInstance()->getAllThings()['NeoWiki']['version'];

		$this->assertStringEndsWith(
			'-' . $version,
			TestingAccessWrapper::newFromObject( new RebuildViolationIndex() )->getUpdateKey()
		);
	}

	public function testTheJobsCheckEveryPageAcrossBatches(): void {
		$adaPageId = $this->createPageWithoutTellingNeoWiki( 'Ada Lovelace', $this->slotJsonOfAPerson( self::ADA ) );
		$gracePageId = $this->createPageWithoutTellingNeoWiki( 'Grace Hopper', $this->slotJsonOfAPerson( self::GRACE ) );

		$this->getServiceContainer()->getJobQueueGroup()->push(
			new JobSpecification( RebuildViolationIndexJob::TYPE, [ 'batchSize' => 1 ] )
		);
		$this->runJobs( [ 'minJobs' => 0 ] );

		$this->assertSame(
			[ [ 'required' ], [ 'required' ] ],
			[ $this->violationCodesOf( $adaPageId ), $this->violationCodesOf( $gracePageId ) ]
		);
	}

	public function testRebuildingClearsTheViolationsOfAPageThatNoLongerHoldsSubjects(): void {
		$goneSinceRecorded = 987654;
		( new DatabaseViolationIndex( $this->getDb() ) )->setViolationsOfPage(
			new PageId( $goneSinceRecorded ),
			new SubjectViolation( new SubjectId( self::ADA ), 'Person', new Violation( new PropertyName( 'Name' ), 'required' ), null )
		);

		$this->rebuild( batchSize: 100 );

		$this->assertSame( [], $this->violationCodesOf( $goneSinceRecorded ) );
	}

	public function testRebuildingChecksEveryPageAcrossBatches(): void {
		$adaPageId = $this->createPageWithoutTellingNeoWiki( 'Ada Lovelace', $this->slotJsonOfAPerson( self::ADA ) );
		$gracePageId = $this->createPageWithoutTellingNeoWiki( 'Grace Hopper', $this->slotJsonOfAPerson( self::GRACE ) );

		$this->rebuild( batchSize: 1 );

		$this->assertSame(
			[ [ 'required' ], [ 'required' ] ],
			[ $this->violationCodesOf( $adaPageId ), $this->violationCodesOf( $gracePageId ) ]
		);
	}

	private function slotJsonOfAPerson( string $subjectId ): string {
		return '{"mainSubject":"' . $subjectId . '","subjects":{"' . $subjectId
			. '":{"label":"Someone","schema":"Person","statements":{}}}}';
	}

	private function rebuild( int $batchSize ): void {
		$rebuilder = new DatabaseViolationIndexRebuilder(
			db: $this->getDb(),
			checker: NeoWikiExtension::getInstance()->newPageViolationChecker(),
			batchSize: $batchSize,
		);

		foreach ( $rebuilder->rebuild() as $ignored ) {
		}
	}

	private function runUpdateScript( string ...$argv ): void {
		$script = new RebuildViolationIndex();
		$script->loadWithArgv( $argv );

		ob_start();
		try {
			$this->assertTrue( $script->execute() );
		} finally {
			$script->cleanupChanneled();
			ob_end_clean();
		}
	}

	/**
	 * @return string[]
	 */
	private function violationCodesOf( int $pageId ): array {
		return $this->getDb()->newSelectQueryBuilder()
			->select( 'nwv_code' )
			->from( DatabaseViolationIndex::TABLE )
			->where( [ 'nwv_page_id' => $pageId ] )
			->orderBy( 'nwv_id' )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

}
