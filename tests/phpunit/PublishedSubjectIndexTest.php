<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\Content\TextContent;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Needs the database, which holds the index, and the integration base class, whose hooks write it.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndex
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 * @group Database
 */
class PublishedSubjectIndexTest extends NeoWikiIntegrationTestCase {

	use ReadsPublishedSubjectIndex;

	private const string PAGE = 'Ada Lovelace';

	// Listed in id order, the order the index is read back in.
	private const string ADA = 's1aada111111111';
	private const string ENGINE = 's1bengine111111';
	private const string NOTE = 's1cnote11111111';
	private const string BIRTH = 's1dbirth1111111';

	private const string BABBAGE = 's1tbabbage11111';
	private const string MARY = 's1tmary11111111';
	private const string NOBODY = 's1tnobody111111';

	private const string MONDAY = '20300107090000';
	private const string TUESDAY = '20300108090000';

	private const string DEPENDENT_SUBJECTS_SCHEMA = '{"subjectsAreDependent":true,"propertyDefinitions":{}}';
	private const string STANDALONE_SUBJECTS_SCHEMA = '{"propertyDefinitions":{}}';

	public function testCreatingAPageIndexesEverySubjectItPublishes(): void {
		$pageId = $this->savePage(
			$this->subject( self::ADA, label: null, schema: 'Person' ),
			$this->subject( self::ENGINE, label: 'Analytical Engine', schema: 'Machine' ),
			$this->subject( self::NOTE, label: null, schema: 'Note' ),
		)->getPageId();

		$this->assertSame(
			[
				self::ADA => [ 'schema' => 'Person', 'displayName' => self::PAGE, 'isMain' => true ],
				self::ENGINE => [ 'schema' => 'Machine', 'displayName' => 'Analytical Engine', 'isMain' => false ],
				self::NOTE => [ 'schema' => 'Note', 'displayName' => null, 'isMain' => false ],
			],
			$this->subjectRowsOf( $pageId )
		);
	}

	public function testASubjectWithASchemaOfAnotherSourceIsIndexedUnderTheSchemasName(): void {
		$pageId = $this->savePage(
			TestSubject::build( id: self::ADA, schemaName: SchemaReference::sourced( 'elsewhere', new SchemaName( 'Person' ) ) )
		)->getPageId();

		$this->assertSame( 'Person', $this->subjectRowsOf( $pageId )[self::ADA]['schema'] );
	}

	public function testMarksTheSubjectsWhoseSchemaDeclaresThemDependent(): void {
		$this->createBirthSchema( self::DEPENDENT_SUBJECTS_SCHEMA );

		$pageId = $this->saveAdaAndHerBirth();

		$this->assertSame( [ self::ADA => false, self::BIRTH => true ], $this->dependentFlagsOf( $pageId ) );
	}

	public function testASchemaCreatedAgainWithTheOtherDeclarationReachesTheRowsOnThePagesNextEdit(): void {
		$this->createBirthSchema( self::DEPENDENT_SUBJECTS_SCHEMA );
		$pageId = $this->saveAdaAndHerBirth();
		$this->deletePageByName( 'Schema:Birth' );
		$this->createBirthSchema( self::STANDALONE_SUBJECTS_SCHEMA );

		$this->editPage( self::PAGE, 'Wikitext only' );

		$this->assertSame( [ self::ADA => false, self::BIRTH => false ], $this->dependentFlagsOf( $pageId ) );
	}

	/**
	 * Maintenance scripts run as an anonymous user, who on a wiki only members may read can read no Schema.
	 */
	public function testMarksADependentSubjectWhoseSchemaTheRequestMayNotRead(): void {
		$this->createBirthSchema( self::DEPENDENT_SUBJECTS_SCHEMA );
		$this->setGroupPermissions( '*', 'read', false );

		$pageId = $this->saveAdaAndHerBirth();

		$this->assertTrue( $this->dependentFlagsOf( $pageId )[self::BIRTH] );
	}

	public function testASchemaNameLongerThanItsColumnIsCutOnACharacterBoundary(): void {
		$pageId = $this->savePage( $this->subject( self::ADA, schema: str_repeat( 'é', 150 ) ) )->getPageId();

		$this->assertSame( str_repeat( 'é', 127 ), $this->subjectRowsOf( $pageId )[self::ADA]['schema'] );
	}

	public function testEveryRelationIsIndexedBySubjectPropertyAndTarget(): void {
		$pageId = $this->savePage(
			$this->subject(
				self::ADA,
				statements: [ $this->relating( 'Mentor', self::BABBAGE, self::MARY ), $this->relating( 'Collaborator', self::BABBAGE ) ]
			),
			$this->subject( self::ENGINE, statements: [ $this->relating( 'Inventor', self::BABBAGE ) ] )
		)->getPageId();

		$this->assertSame(
			[
				[ self::ADA, 'Collaborator', self::BABBAGE ],
				[ self::ADA, 'Mentor', self::BABBAGE ],
				[ self::ADA, 'Mentor', self::MARY ],
				[ self::ENGINE, 'Inventor', self::BABBAGE ],
			],
			$this->relationsOf( $pageId )
		);
	}

	public function testARelationToASubjectOfAnotherSourceIsNotIndexed(): void {
		$pageId = $this->savePage(
			$this->subject( self::ADA, statements: [ $this->relating( 'Mentor', 'elsewhere:' . self::BABBAGE, self::MARY ) ] )
		)->getPageId();

		$this->assertSame( [ [ self::ADA, 'Mentor', self::MARY ] ], $this->relationsOf( $pageId ) );
	}

	public function testARelationToASubjectNobodyCreatedIsIndexed(): void {
		$pageId = $this->savePage(
			$this->subject( self::ADA, statements: [ $this->relating( 'Mentor', self::NOBODY ) ] )
		)->getPageId();

		$this->assertSame( [ [ self::ADA, 'Mentor', self::NOBODY ] ], $this->relationsOf( $pageId ) );
	}

	public function testAPropertyNameLongerThanItsColumnIsCutOnACharacterBoundary(): void {
		$pageId = $this->savePage(
			$this->subject( self::ADA, statements: [ $this->relating( str_repeat( 'é', 150 ), self::BABBAGE ) ] )
		)->getPageId();

		$this->assertSame( [ [ self::ADA, str_repeat( 'é', 127 ), self::BABBAGE ] ], $this->relationsOf( $pageId ) );
	}

	public function testChangingALabelRenamesThatSubjectAlone(): void {
		$pageId = $this->savePage(
			$this->subject( self::ADA, label: 'Ada' ),
			$this->subject( self::ENGINE, label: 'Engine' )
		)->getPageId();

		$this->savePage( $this->subject( self::ADA, label: 'Countess' ), $this->subject( self::ENGINE, label: 'Engine' ) );

		$this->assertSame( [ self::ADA => 'Countess', self::ENGINE => 'Engine' ], $this->displayNamesOf( $pageId ) );
	}

	/**
	 * One time for both saves, so that only the relations differ.
	 */
	public function testRemovingRelationsRemovesTheirRowsAlone(): void {
		ConvertibleTimestamp::setFakeTime( self::MONDAY );
		$pageId = $this->savePage(
			$this->subject( self::ADA, statements: [ $this->relating( 'Mentor', self::BABBAGE, self::MARY, self::NOBODY ) ] )
		)->getPageId();

		$this->savePage( $this->subject( self::ADA, statements: [ $this->relating( 'Mentor', self::BABBAGE ) ] ) );

		$this->assertSame( [ [ self::ADA, 'Mentor', self::BABBAGE ] ], $this->relationsOf( $pageId ) );
	}

	public function testRemovingASubjectRemovesItsRowAndItsRelations(): void {
		$pageId = $this->savePage(
			$this->subject( self::ADA ),
			$this->subject( self::ENGINE, statements: [ $this->relating( 'Inventor', self::BABBAGE ) ] )
		)->getPageId();

		$this->savePage( $this->subject( self::ADA ) );

		$this->assertSame( [ self::ADA ], array_keys( $this->subjectRowsOf( $pageId ) ) );
		$this->assertSame( [], $this->relationsOf( $pageId ) );
	}

	public function testRollingBackToBeforeThePageHadSubjectsRemovesTheirRows(): void {
		$this->editPage( self::PAGE, new TextContent( 'Before the Subjects' ), '', NS_MAIN, $this->getTestUser()->getAuthority() );
		$pageId = $this->savePage(
			$this->subject( self::ADA, statements: [ $this->relating( 'Mentor', self::BABBAGE ) ] )
		)->getPageId();
		$this->assertNotSame( [], $this->relationsOf( $pageId ), 'precondition: the page has rows' );

		$this->rollBack( self::PAGE );

		$this->assertSame( [], $this->subjectRowsOf( $pageId ) );
		$this->assertSame( [], $this->relationsOf( $pageId ) );
	}

	public function testRepublishingWhatThePageAlreadyPublishesWritesNothing(): void {
		$revision = $this->savePage(
			$this->subject( self::ADA ),
			$this->subject( self::ENGINE, statements: [ $this->relating( 'Inventor', self::BABBAGE ) ] )
		);
		$rowsBefore = [ $this->subjectRowsOf( $revision->getPageId() ), $this->relationsOf( $revision->getPageId() ) ];

		$this->newIndexThatCannotWrite()->setSubjectsOfPage(
			new PageId( $revision->getPageId() ),
			self::PAGE,
			$this->subjectsOf( $revision ),
			(string)$revision->getTimestamp()
		);

		$this->assertSame(
			$rowsBefore,
			[ $this->subjectRowsOf( $revision->getPageId() ), $this->relationsOf( $revision->getPageId() ) ]
		);
	}

	public function testAnEditLeavingTheSubjectsAloneMovesTheirEditTime(): void {
		ConvertibleTimestamp::setFakeTime( self::MONDAY );
		$pageId = $this->savePage( $this->subject( self::ADA ), $this->subject( self::ENGINE ) )->getPageId();

		ConvertibleTimestamp::setFakeTime( self::TUESDAY );
		$this->editPage( self::PAGE, 'Wikitext only' );

		$this->assertSame( [ self::ADA => self::TUESDAY, self::ENGINE => self::TUESDAY ], $this->editTimesOf( $pageId ) );
	}

	public function testDeletingAPageRemovesItsRows(): void {
		$pageId = $this->savePage(
			$this->subject( self::ADA, statements: [ $this->relating( 'Mentor', self::BABBAGE ) ] )
		)->getPageId();
		$this->assertNotSame( [], $this->relationsOf( $pageId ), 'precondition: the page has rows' );

		$this->deletePageByName( self::PAGE );

		$this->assertSame( [], $this->subjectRowsOf( $pageId ) );
		$this->assertSame( [], $this->relationsOf( $pageId ) );
	}

	public function testUndeletingAPageIndexesItsSubjectsAgain(): void {
		$pageId = $this->savePage( $this->subject( self::ADA, label: 'Ada' ) )->getPageId();
		$this->deletePageByName( self::PAGE );

		$this->undeleteArchivedRevisions( self::PAGE );

		$this->assertSame( [ self::ADA => 'Ada' ], $this->displayNamesOf( $pageId ) );
	}

	public function testImportingAPageIndexesItsSubjects(): void {
		$this->savePage( $this->subject( self::ADA, label: 'Ada' ) );
		$xml = $this->exportPageToXml( self::PAGE );
		$this->deletePageByName( self::PAGE );

		$this->importXml( $xml );

		$this->assertSame( [ self::ADA => 'Ada' ], $this->displayNamesOf( $this->currentPageId( self::PAGE ) ) );
	}

	public function testMovingAPageKeysItsRowsUnderTheNewName(): void {
		$pageId = $this->savePage( $this->subject( self::ADA ), $this->subject( self::ENGINE ) )->getPageId();

		$this->movePage( self::PAGE, 'Countess of Lovelace' );

		$this->assertSame(
			[ self::ADA => 'countess of lovelace', self::ENGINE => 'countess of lovelace' ],
			$this->pageKeysOf( $pageId )
		);
	}

	public function testMovingAPageToAnotherNamespaceRenamesTheMainSubjectNamedAfterIt(): void {
		$pageId = $this->savePage(
			$this->subject( self::ADA, label: null ),
			$this->subject( self::ENGINE, label: 'Analytical Engine' )
		)->getPageId();

		$this->movePage( self::PAGE, 'Help:Countess of Lovelace' );

		$this->assertSame(
			[ self::ADA => 'Help:Countess of Lovelace', self::ENGINE => 'Analytical Engine' ],
			$this->displayNamesOf( $pageId )
		);
	}

	public function testMovingAPageThatPublishesAnOlderRevisionRenamesItsMainSubject(): void {
		$approved = $this->savePage( $this->subject( self::ADA, label: null ) );
		$this->savePage( $this->subject( self::ADA, label: 'Draft name' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );

		$this->movePage( self::PAGE, 'Countess of Lovelace' );

		$this->assertSame( [ self::ADA => 'Countess of Lovelace' ], $this->displayNamesOf( $approved->getPageId() ) );
	}

	public function testIndexesTheRevisionThePolicyPublishes(): void {
		ConvertibleTimestamp::setFakeTime( self::MONDAY );
		$approved = $this->savePage( $this->subject( self::ADA, label: 'Approved name' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );

		ConvertibleTimestamp::setFakeTime( self::TUESDAY );
		$this->savePage( $this->subject( self::ADA, label: 'Draft name' ) );

		$this->assertSame( [ self::ADA => 'Approved name' ], $this->displayNamesOf( $approved->getPageId() ) );
		$this->assertSame( [ self::ADA => self::MONDAY ], $this->editTimesOf( $approved->getPageId() ) );
	}

	public function testAPageThatPublishesNothingHasNoRows(): void {
		$pageId = $this->savePage(
			$this->subject( self::ADA, statements: [ $this->relating( 'Mentor', self::BABBAGE ) ] )
		)->getPageId();
		$this->assertNotSame( [], $this->relationsOf( $pageId ), 'precondition: the page has rows' );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishingNothingFromPage( $pageId ) );

		$this->savePage( $this->subject( self::ADA, label: 'Draft name' ) );

		$this->assertSame( [], $this->subjectRowsOf( $pageId ) );
		$this->assertSame( [], $this->relationsOf( $pageId ) );
	}

	public function testAnApprovalRefreshIndexesTheNewlyPublishedRevision(): void {
		$approved = $this->savePage( $this->subject( self::ADA, label: 'Approved name' ) );
		$this->savePage( $this->subject( self::ADA, label: 'Draft name' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );

		NeoWikiExtension::getInstance()->newPageRebuilder()->rebuild( Title::newFromText( self::PAGE ) );

		$this->assertSame( [ self::ADA => 'Approved name' ], $this->displayNamesOf( $approved->getPageId() ) );
	}

	public function testRebuildingAGraphStoreLeavesTheIndexAlone(): void {
		$approved = $this->savePage( $this->subject( self::ADA, label: 'Approved name' ) );
		$this->savePage( $this->subject( self::ADA, label: 'Draft name' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );

		NeoWikiExtension::getInstance()
			->newPageRebuilderFor( new SpyGraphDatabasePlugin() )
			->rebuild( Title::newFromText( self::PAGE ) );

		$this->assertSame( [ self::ADA => 'Draft name' ], $this->displayNamesOf( $approved->getPageId() ) );
	}

	private function createBirthSchema( string $json ): void {
		$this->assertNotNull( $this->createSchema( 'Birth', $json ) );
	}

	private function saveAdaAndHerBirth(): int {
		return $this->savePage(
			$this->subject( self::ADA, statements: [ $this->relating( 'Birth', self::BIRTH ) ] ),
			$this->subject( self::BIRTH, label: null, schema: 'Birth' )
		)->getPageId();
	}

	private function savePage( Subject $mainSubject, Subject ...$otherSubjects ): RevisionRecord {
		$revision = $this->createPageWithSubjects( self::PAGE, $mainSubject, new SubjectMap( ...$otherSubjects ) );
		$this->assertNotNull( $revision );

		return $revision;
	}

	/**
	 * @param Statement[] $statements
	 */
	private function subject(
		string $id,
		?string $label = 'Some label',
		string $schema = 'Person',
		array $statements = []
	): Subject {
		return TestSubject::build(
			id: $id,
			label: $label,
			schemaName: new SchemaName( $schema ),
			statements: new StatementList( $statements )
		);
	}

	private function relating( string $property, string ...$targetIds ): Statement {
		return TestStatement::buildRelation(
			$property,
			array_map( static fn ( string $targetId ): Relation => TestRelation::build( targetId: $targetId ), $targetIds )
		);
	}

	private function subjectsOf( RevisionRecord $revision ): PageSubjects {
		$content = $revision->getContent( MediaWikiSubjectRepository::SLOT_NAME );
		$this->assertInstanceOf( SubjectContent::class, $content );

		return $content->getPageSubjects();
	}

	private function newIndexThatCannotWrite(): DatabasePublishedSubjectIndex {
		return new DatabasePublishedSubjectIndex(
			$this->getServiceContainer()->getConnectionProvider()->getReplicaDatabase(),
			NeoWikiExtension::getInstance()->newUnrestrictedDependentSubjectRules()
		);
	}

	private function currentPageId( string $pageName ): int {
		return Title::newFromText( $pageName )->getArticleID( IDBAccessObject::READ_LATEST );
	}

	private function rollBack( string $pageName ): void {
		$this->assertStatusGood(
			$this->getServiceContainer()->getRollbackPageFactory()->newRollbackPage(
				Title::newFromText( $pageName ),
				$this->getTestSysop()->getAuthority(),
				$this->getTestSysop()->getUser()
			)->rollback()
		);
	}

	/**
	 * @return array<string, string> By Subject id
	 */
	private function pageKeysOf( int $pageId ): array {
		$keys = [];

		foreach ( $this->selectSubjectRows( $pageId ) as $row ) {
			$keys[$row->nws_subject_id] = $row->nws_page_key;
		}

		return $keys;
	}

}
