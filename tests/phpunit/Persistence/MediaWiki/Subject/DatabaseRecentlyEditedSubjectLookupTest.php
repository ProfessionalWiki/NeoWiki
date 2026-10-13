<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummary;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\DatabaseRecentlyEditedSubjectLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Needs the database for the revision history it reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\DatabaseRecentlyEditedSubjectLookup
 * @group Database
 */
class DatabaseRecentlyEditedSubjectLookupTest extends NeoWikiIntegrationTestCase {

	private const string ADA = 'sTestRES1111111';
	private const string GRACE = 'sTestRES1111112';

	// After the fixture's Schema page, which is saved at the real time.
	private const string MONDAY = '2030-01-07T09:00:00Z';
	private const string TUESDAY = '2030-01-08T09:00:00Z';
	private const string WEDNESDAY = '2030-01-09T09:00:00Z';

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	/**
	 * An import saves edits with their original times, so the order they were saved in can differ.
	 */
	public function testListsTheSubjectsEditedMostRecentlyFirst(): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		$this->saveAt( self::WEDNESDAY, 'Grace Hopper', $this->person( self::GRACE, '1906' ) );
		$this->saveAt( self::TUESDAY, 'Ada Lovelace', $this->person( self::ADA, '1816' ) );

		$this->assertSame( [ self::WEDNESDAY => [ self::GRACE ], self::TUESDAY => [ self::ADA ] ], $this->editsByTime() );
	}

	public function testAnEditListsOnlyTheSubjectItChanged(): void {
		$this->saveAt( self::MONDAY, 'Pioneers', $this->person( self::ADA, '1815' ), $this->person( self::GRACE, '1906' ) );
		$this->saveAt( self::TUESDAY, 'Pioneers', $this->person( self::ADA, '1815' ), $this->person( self::GRACE, '1907' ) );
		$this->saveAt( self::WEDNESDAY, 'Pioneers', $this->person( self::ADA, '1815' ), $this->person( self::GRACE, '1908' ) );

		$this->assertSame( [ self::WEDNESDAY => [ self::GRACE ], self::MONDAY => [ self::ADA ] ], $this->editsByTime() );
	}

	/**
	 * @dataProvider editedSubjectProvider
	 */
	public function testALabelOrSchemaChangeIsAnEdit( Subject $edited ): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', TestSubject::build( id: self::ADA, label: 'Ada' ) );
		$this->saveAt( self::TUESDAY, 'Ada Lovelace', $edited );

		$this->assertSame( [ self::TUESDAY => [ self::ADA ] ], $this->editsByTime() );
	}

	public static function editedSubjectProvider(): iterable {
		yield 'label' => [ TestSubject::build( id: self::ADA, label: 'Countess' ) ];
		yield 'Schema' => [ TestSubject::build( id: self::ADA, label: 'Ada', schemaName: new SchemaName( 'Mathematician' ) ) ];
	}

	public function testATextEditListsNothing(): void {
		$this->assertStatusGood( $this->editPage( 'Ada Lovelace', new TextContent( 'A page before it held a Subject' ) ) );
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		ConvertibleTimestamp::setFakeTime( self::TUESDAY );
		$this->assertStatusGood( $this->editPage( 'Ada Lovelace', new TextContent( 'Text only' ) ) );

		$this->assertSame( [ self::MONDAY => [ self::ADA ] ], $this->editsByTime() );
	}

	public function testARollbackIsAnEdit(): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		$this->changeSubjectsAsAnotherUserAt( self::TUESDAY, 'Ada Lovelace', $this->person( self::ADA, '1816' ) );

		ConvertibleTimestamp::setFakeTime( self::WEDNESDAY );
		$this->assertStatusGood( $this->getServiceContainer()->getRollbackPageFactory()->newRollbackPage(
			Title::newFromText( 'Ada Lovelace' ),
			$this->getTestSysop()->getAuthority(),
			$this->getTestUser()->getUser()
		)->rollback() );

		$this->assertSame( [ self::WEDNESDAY => [ self::ADA ] ], $this->editsByTime() );
	}

	public function testMakingAnotherSubjectTheMainOneListsNothing(): void {
		$this->saveAt( self::MONDAY, 'Pioneers', $this->person( self::ADA, '1815' ), $this->person( self::GRACE, '1906' ) );
		$this->saveAt( self::TUESDAY, 'Pioneers', $this->person( self::GRACE, '1906' ), $this->person( self::ADA, '1815' ) );

		$this->assertSame( [ self::MONDAY => [ self::ADA, self::GRACE ] ], $this->editsByTime() );
	}

	public function testTheSameDataWrittenInAnotherFormListsNothing(): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', TestSubject::build(
			id: self::ADA,
			label: 'Ada',
			statements: new StatementList( [ $this->statement( 'Born', '1815' ), $this->statement( 'Died', '1852' ) ] )
		) );
		$this->writeSubjectSlotAt(
			self::TUESDAY,
			'Ada Lovelace',
			'{"subjects":{"' . self::ADA . '":{"statements":{"Died":{"value":["1852"],"propertyType":"text"},'
				. '"Born":{"propertyType":"text","value":["1815"]}},"schema":"' . TestSubject::DEFAULT_SCHEMA_ID
				. '","label":"Ada"}},"mainSubject":"' . self::ADA . '"}'
		);

		$this->assertSame( [ self::MONDAY => [ self::ADA ] ], $this->editsByTime() );
	}

	public function testLeavesOutARemovedSubject(): void {
		$this->saveAt( self::MONDAY, 'Pioneers', $this->person( self::ADA, '1815' ), $this->person( self::GRACE, '1906' ) );
		$this->saveAt( self::TUESDAY, 'Pioneers', $this->person( self::ADA, '1816' ), $this->person( self::GRACE, '1906' ) );
		$this->saveAt( self::WEDNESDAY, 'Pioneers', null, $this->person( self::GRACE, '1906' ) );

		$this->assertSame( [ self::MONDAY => [ self::GRACE ] ], $this->editsByTime() );
	}

	public function testListsAMovedSubjectOnThePageItMovedToAtTheTimeOfTheMove(): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		$engine = $this->saveAt( self::TUESDAY, 'Analytical Engine', $this->person( self::GRACE, '1906' ) );
		$this->saveAt( self::WEDNESDAY, 'Ada Lovelace', null );
		$this->saveAt( self::WEDNESDAY, 'Analytical Engine', $this->person( self::GRACE, '1906' ), $this->person( self::ADA, '1815' ) );

		$ada = $this->listedRow( self::ADA );

		$this->assertSame(
			[ self::WEDNESDAY, $engine->getPageId(), 'Analytical Engine' ],
			[ $ada->lastEdited, $ada->pageId, $ada->pageTitle ]
		);
	}

	public function testLeavesOutSubjectsOnPagesTheReaderMayNotRead(): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		$secret = $this->saveAt( self::TUESDAY, 'Grace Hopper', $this->person( self::GRACE, '1906' ) );

		$lookup = $this->newLookup( readAuthorizer: new SelectivePageReadAuthorizer( [ $secret->getPageId() ] ) );

		$this->assertSame( [ self::ADA ], array_column( $lookup->getRecentlyEditedSubjects( 10 ), 'subjectId' ) );
	}

	public function testAnEditWhoseContentIsHiddenListsNothing(): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		$hidden = $this->saveAt( self::TUESDAY, 'Ada Lovelace', $this->person( self::ADA, '1816' ) );
		// MediaWiki refuses to hide the current revision's content.
		$this->editPage( 'Ada Lovelace', new TextContent( 'Text only' ) );
		$this->hideRevisionContent( $hidden );

		$this->assertSame( [ self::MONDAY => [ self::ADA ] ], $this->editsByTime() );
	}

	/**
	 * Comparing with hidden content would tell which Subjects it held.
	 */
	public function testAnEditAfterHiddenContentListsEachSubjectItHolds(): void {
		$hidden = $this->saveAt( self::MONDAY, 'Pioneers', $this->person( self::ADA, '1815' ), $this->person( self::GRACE, '1906' ) );
		$this->saveAt( self::TUESDAY, 'Pioneers', $this->person( self::ADA, '1816' ), $this->person( self::GRACE, '1906' ) );
		$this->hideRevisionContent( $hidden );

		$this->assertSame( [ self::TUESDAY => [ self::ADA, self::GRACE ] ], $this->editsByTime() );
	}

	public function testListsTheSubjectsAsTheWikiPublishesThem(): void {
		$approved = $this->saveAt( self::MONDAY, 'Ada Lovelace', TestSubject::build( id: self::ADA, label: 'Ada' ) );
		$this->saveAt( self::TUESDAY, 'Ada Lovelace', TestSubject::build( id: self::ADA, label: 'Countess' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );

		$ada = $this->listedRow( self::ADA );

		$this->assertSame( [ self::MONDAY, 'Ada' ], [ $ada->lastEdited, $ada->displayName ] );
	}

	public function testLeavesOutThePagesThatPublishNothing(): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		$waiting = $this->saveAt( self::TUESDAY, 'Grace Hopper', $this->person( self::GRACE, '1906' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishingNothingFromPage( $waiting->getPageId() ) );

		$this->assertSame( [ self::MONDAY => [ self::ADA ] ], $this->editsByTime() );
	}

	public function testLooksBackNoFurtherThanItsRevisionBound(): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		$this->saveAt( self::TUESDAY, 'Grace Hopper', $this->person( self::GRACE, '1906' ) );
		$this->editPage( 'Grace Hopper', new TextContent( 'Text only' ) );

		$this->assertSame( [ self::GRACE ], array_column( $this->newLookup( revisionBound: 2 )->getRecentlyEditedSubjects( 10 ), 'subjectId' ) );
	}

	public function testEndsTheListOnceItHasReadItsBound(): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		$this->saveAt( self::TUESDAY, 'Grace Hopper', $this->person( self::GRACE, '1906' ) );

		$this->assertNotContains( self::ADA, array_column( $this->newLookup( readBound: 1 )->getRecentlyEditedSubjects( 10 ), 'subjectId' ) );
	}

	public function testListsNothingOnAWikiThatNeverStoredASubject(): void {
		$this->truncateTable( 'slot_roles' );
		$this->resetServices();

		$this->assertSame( [], $this->newLookup()->getRecentlyEditedSubjects( 10 ) );
	}

	/**
	 * @dataProvider brokenSubjectJsonProvider
	 */
	public function testLeavesOutAPageWhoseSubjectsDoNotDeserialize( string $subjectJson ): void {
		$this->saveAt( self::MONDAY, 'Ada Lovelace', $this->person( self::ADA, '1815' ) );
		// Saving through NeoWiki would refuse what does not deserialize.
		$this->clearHook( 'RevisionFromEditComplete' );
		$this->writeSubjectSlotAt( self::TUESDAY, 'Grace Hopper', $subjectJson );

		$this->assertSame( [ self::MONDAY => [ self::ADA ] ], $this->editsByTime() );
	}

	public static function brokenSubjectJsonProvider(): iterable {
		yield 'a Subject that fails validation' => [ TestSubject::jsonThatDoesNotDeserialize( self::GRACE ) ];
		yield 'a Subject that is not an object' => [ '{"mainSubject":"' . self::GRACE . '","subjects":{"' . self::GRACE . '":1}}' ];
	}

	public function testNamesSubjectsNobodyNamedAsTheSubjectListDoes(): void {
		$this->saveAt( self::MONDAY, 'Help:Ada Lovelace', TestSubject::build( id: self::ADA, label: null ), TestSubject::build( id: self::GRACE, label: null ) );

		$main = $this->listedRow( self::ADA );
		$other = $this->listedRow( self::GRACE );

		$this->assertSame( [ 'Help:Ada Lovelace', false ], [ $main->displayName, $main->displayNameIsGenerated ] );
		$this->assertSame( [ TestSubject::DEFAULT_SCHEMA_ID, true ], [ $other->displayName, $other->displayNameIsGenerated ] );
		$this->assertSame( 'Help:Ada Lovelace', $other->pageTitle );
	}

	private function newLookup(
		int $revisionBound = 2000,
		int $readBound = 200,
		PageReadAuthorizer $readAuthorizer = new StubPageReadAuthorizer( true )
	): DatabaseRecentlyEditedSubjectLookup {
		return new DatabaseRecentlyEditedSubjectLookup(
			connectionProvider: $this->getServiceContainer()->getConnectionProvider(),
			slotRoleStore: $this->getServiceContainer()->getSlotRoleStore(),
			revisionLookup: $this->getServiceContainer()->getRevisionLookup(),
			revisionPolicy: NeoWikiExtension::getInstance()->getRevisionPolicy(),
			readAuthorizer: $readAuthorizer,
			titleFormatter: $this->getServiceContainer()->getTitleFormatter(),
			revisionBound: $revisionBound,
			readBound: $readBound,
		);
	}

	/**
	 * Listed Subject ids by edit time, newest first; those of one edit in id order, as they have none of their own.
	 *
	 * @return array<string, string[]>
	 */
	private function editsByTime(): array {
		$edits = [];

		foreach ( $this->newLookup()->getRecentlyEditedSubjects( 50 ) as $summary ) {
			$edits[$summary->lastEdited][] = $summary->subjectId;
			sort( $edits[$summary->lastEdited] );
		}

		return $edits;
	}

	private function listedRow( string $subjectId ): SubjectSummary {
		return array_column( $this->newLookup()->getRecentlyEditedSubjects( 50 ), null, 'subjectId' )[$subjectId];
	}

	private function person( string $id, string $born ): Subject {
		return TestSubject::build( id: $id, statements: new StatementList( [ $this->statement( 'Born', $born ) ] ) );
	}

	private function statement( string $property, string $value ): Statement {
		return new Statement( new PropertyName( $property ), 'text', new StringValue( $value ) );
	}

	/** Creates the page, or changes only its Subjects as the Subject editor does. */
	private function saveAt( string $time, string $pageName, ?Subject $mainSubject, Subject ...$otherSubjects ): RevisionRecord {
		ConvertibleTimestamp::setFakeTime( $time );
		$subjects = new SubjectMap( ...$otherSubjects );

		$revision = Title::newFromText( $pageName )->exists()
			? $this->changeSubjectsOfPage( $pageName, $mainSubject, $subjects )
			: $this->createPageWithSubjects( $pageName, $mainSubject, $subjects );
		$this->assertNotNull( $revision );

		return $revision;
	}

	private function changeSubjectsAsAnotherUserAt( string $time, string $pageName, Subject $mainSubject ): void {
		ConvertibleTimestamp::setFakeTime( $time );
		$updater = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) )
			->newPageUpdater( $this->getTestUser()->getUser() );
		$updater->setContent(
			MediaWikiSubjectRepository::SLOT_NAME,
			SubjectContent::newFromData( new PageSubjects( $mainSubject, new SubjectMap() ) )
		);

		$this->assertNotNull( $updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Edit by another user' ) ) );
	}

	private function writeSubjectSlotAt( string $time, string $pageName, string $subjectJson ): void {
		ConvertibleTimestamp::setFakeTime( $time );
		$this->assertNotNull( $this->writeSubjectSlot( $pageName, $subjectJson ) );
	}

}
