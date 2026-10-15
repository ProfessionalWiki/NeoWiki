<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetSubject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectResponseItemFactory;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Tests\Data\TestNaming;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery
 */
class GetSubjectQueryTest extends TestCase {

	private const string PERSON = 'sGSQPerson11111';
	private const string FRIEND = 'sGSQFriend11111';
	private const string BIRTH = 'sGSQBirth111111';
	private const string RECORD = 'sGSQRecord11111';
	private const string PLACE = 'sGSQSpain111111';
	private const string ARCHIVE = 'sGSQArchive1111';

	public function testPresentsSubjectInHappyPathResponse(): void {
		$spyPresenter = $this->getSpyPresenter();

		$query = $this->newQuery(
			$spyPresenter,
			new InMemorySubjectLookup(
				TestSubject::build(),
				TestSubject::build(
					id: 's11111111111129',
					label: new SubjectLabel( 'expected label' ),
					schemaName: new SchemaName( 'GetSubjectQueryTestSchema' ),
					statements: new StatementList( [
						TestStatement::build( 'expected property 1', 'expected value 1' ),
						TestStatement::build( 'expected property 2', value: new NumberValue( 2 ), propertyType: 'number' ),
						TestStatement::build(
							'FriendOf',
							new RelationValue( TestRelation::build(
								id: 'r11111111111129',
								targetId: 's11111111111131',
							) ),
							RelationType::NAME,
						),
					] ),
				),
			)
		);

		$query->execute(
			subjectId: 's11111111111129',
			includePageIdentifiers: false,
			includeReferencedSubjects: false
		);

		$this->assertEquals(
			new GetSubjectResponse(
				's11111111111129',
				[
					's11111111111129' => new GetSubjectResponseItem(
						id: 's11111111111129',
						label: 'expected label',
						displayName: 'expected label',
						displayNameIsGenerated: false,
						schema: 'GetSubjectQueryTestSchema',
						statements: [
							'expected property 1' => [
								'propertyType' => 'text',
								'value' => [ 'expected value 1' ]
							],
							'expected property 2' => [
								'propertyType' => 'number',
								'value' => 2
							],
							'FriendOf' => [
								'propertyType' => 'relation',
								'value' => [
									[
										'id' => 'r11111111111129',
										'target' => 's11111111111131',
									]
								]
							]
						],
						pageId: null,
						pageTitle: null,
						pageNamespaceId: null,
						hostSubject: null,
					)
				]
			),
			$spyPresenter->response
		);
	}

	private function getSpyPresenter(): object {
		return new class() implements GetSubjectPresenter {

			public GetSubjectResponse $response;
			public bool $notFound = false;

			public function presentSubject( GetSubjectResponse $response ): void {
				$this->response = $response;
			}

			public function presentSubjectNotFound(): void {
				$this->notFound = true;
			}

		};
	}

	public function testPresentsSubjectNotFound(): void {
		$spyPresenter = $this->getSpyPresenter();

		$query = $this->newQuery(
			$spyPresenter,
			new InMemorySubjectLookup()
		);

		$query->execute(
			subjectId: TestSubject::ZERO_GUID,
			includePageIdentifiers: false,
			includeReferencedSubjects: false
		);

		$this->assertTrue( $spyPresenter->notFound );
	}

	public function testIncludePageIdentifiers(): void {
		$spyPresenter = $this->getSpyPresenter();
		$subject = TestSubject::build();

		$query = $this->newQuery(
			$spyPresenter,
			new InMemorySubjectLookup( $subject ),
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( TestSubject::ZERO_GUID ), new PageIdentifiers( new PageId( 1 ), 'wrong title', 0 ) ],
				[ $subject->id, new PageIdentifiers( new PageId( 42 ), 'right title', 12 ) ],
			] )
		);

		$query->execute(
			subjectId: $subject->getId()->text,
			includePageIdentifiers: true,
			includeReferencedSubjects: false
		);

		$response = $spyPresenter->response;

		$this->assertSame( 42, $response->subjects[$response->requestedId]->pageId );
		$this->assertSame( 'right title', $response->subjects[$response->requestedId]->pageTitle );
		$this->assertSame( 12, $response->subjects[$response->requestedId]->pageNamespaceId );
	}

	public function testLabellessMainSubjectIsNamedAfterItsPage(): void {
		$spyPresenter = $this->getSpyPresenter();
		$subject = TestSubject::build( id: 's11111111111maa', label: null );

		$this->newQueryForLabellessSubject( $spyPresenter, $subject, $subject )->execute(
			subjectId: 's11111111111maa',
			includePageIdentifiers: true,
			includeReferencedSubjects: false
		);

		$this->assertNull( $spyPresenter->response->subjects['s11111111111maa']->label );
		$this->assertSame( 'Rijksmuseum', $spyPresenter->response->subjects['s11111111111maa']->displayName );
		$this->assertFalse( $spyPresenter->response->subjects['s11111111111maa']->displayNameIsGenerated );
	}

	/**
	 * The page identifiers are withheld from the response unless expand=page asked for them, but the
	 * display name is built before that trimming: a default read of a label-less Main Subject must
	 * still be named after its page rather than falling through to its Schema.
	 */
	public function testLabellessMainSubjectIsNamedAfterItsPageWithoutThePageExpansion(): void {
		$spyPresenter = $this->getSpyPresenter();
		$subject = TestSubject::build( id: 's11111111111maa', label: null );

		$this->newQueryForLabellessSubject( $spyPresenter, $subject, $subject )->execute(
			subjectId: 's11111111111maa',
			includePageIdentifiers: false,
			includeReferencedSubjects: false
		);

		$this->assertNull( $spyPresenter->response->subjects['s11111111111maa']->pageTitle );
		$this->assertSame( 'Rijksmuseum', $spyPresenter->response->subjects['s11111111111maa']->displayName );
		$this->assertFalse( $spyPresenter->response->subjects['s11111111111maa']->displayNameIsGenerated );
	}

	public function testLabellessOtherSubjectIsNamedAfterItsSchema(): void {
		$spyPresenter = $this->getSpyPresenter();
		$otherSubject = TestSubject::build(
			id: 's11111111111ca1',
			label: null,
			schemaName: new SchemaName( 'Attendance' )
		);

		$this->newQueryForLabellessSubject(
			$spyPresenter,
			$otherSubject,
			TestSubject::build( id: 's11111111111maa' )
		)->execute(
			subjectId: 's11111111111ca1',
			includePageIdentifiers: true,
			includeReferencedSubjects: false
		);

		$this->assertNull( $spyPresenter->response->subjects['s11111111111ca1']->label );
		$this->assertSame( 'Attendance', $spyPresenter->response->subjects['s11111111111ca1']->displayName );
		$this->assertTrue( $spyPresenter->response->subjects['s11111111111ca1']->displayNameIsGenerated );
	}

	public function testLabellessMainSubjectOfAPageTitledByAnotherSubjectsIdIsNamedAfterItsSchema(): void {
		$spyPresenter = $this->getSpyPresenter();
		$mainSubject = TestSubject::build( id: 's11111111111maa', label: null, schemaName: new SchemaName( 'Museum' ) );
		$otherSubject = TestSubject::build( id: 's11111111111ca1' );

		$this->newQueryForLabellessSubject(
			$spyPresenter,
			$mainSubject,
			$mainSubject,
			pageTitle: ucfirst( $otherSubject->id->text ),
			otherSubjectsOfPage: new SubjectMap( $otherSubject )
		)->execute(
			subjectId: 's11111111111maa',
			includePageIdentifiers: true,
			includeReferencedSubjects: false
		);

		$this->assertSame( 'Museum', $spyPresenter->response->subjects['s11111111111maa']->displayName );
		$this->assertTrue( $spyPresenter->response->subjects['s11111111111maa']->displayNameIsGenerated );
	}

	private function newQueryForLabellessSubject(
		object $spyPresenter,
		Subject $requested,
		Subject $mainSubjectOfPage,
		string $pageTitle = 'Rijksmuseum',
		SubjectMap $otherSubjectsOfPage = new SubjectMap()
	): GetSubjectQuery {
		return $this->newQuery(
			$spyPresenter,
			new InMemorySubjectLookup( $requested ),
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ $requested->id, new PageIdentifiers( new PageId( 42 ), $pageTitle, 0 ) ],
			] ),
			responseItemFactory: $this->responseItemFactoryWithMainSubject( $mainSubjectOfPage, $otherSubjectsOfPage, 42 )
		);
	}

	public function testIncludeReferencedSubjects(): void {
		$spyPresenter = $this->getSpyPresenter();
		$schemaName = new SchemaName( 'GetSubjectQueryTest' );

		$subject = TestSubject::build(
			id: 's11111111111132',
			label: new SubjectLabel( 'requested subject' ),
			schemaName: $schemaName,
			statements: new StatementList( [
				TestStatement::build(
					'FriendOf',
					new RelationValue( TestRelation::build(
						id: 'r11111111111134',
						targetId: 's11111111111133',
					) ),
					RelationType::NAME,
				),
			] )
		);

		$referencedSubject = TestSubject::build(
			id: 's11111111111133',
			label: new SubjectLabel( 'referenced subject' ),
		);

		$query = $this->newQuery(
			$spyPresenter,
			new InMemorySubjectLookup( $subject, $referencedSubject ),
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ $subject->id, new PageIdentifiers( new PageId( 42 ), 'subject title', 0 ) ],
				[ $referencedSubject->id, new PageIdentifiers( new PageId( 1337 ), 'referenced title', 12 ) ],
			] )
		);

		$query->execute(
			subjectId: $subject->getId()->text,
			includePageIdentifiers: true,
			includeReferencedSubjects: true
		);

		$response = $spyPresenter->response;

		$this->assertSame( 'requested subject', $response->subjects[$subject->id->text]->label );

		$this->assertSame( 'referenced subject', $response->subjects[$referencedSubject->id->text]->label );
		$this->assertSame( 1337, $response->subjects[$referencedSubject->id->text]->pageId );
		$this->assertSame( 'referenced title', $response->subjects[$referencedSubject->id->text]->pageTitle );
		$this->assertSame( 12, $response->subjects[$referencedSubject->id->text]->pageNamespaceId );
	}

	/**
	 * The counts, not the response, are what this pins: every referenced Subject is resolved by the
	 * two batch calls, so adding targets does not add round trips. The per-id counts must stay at
	 * the one the requested Subject itself costs - resolving targets one at a time inside the loop
	 * keeps the batch calls at one apiece while reinstating the round trips they remove.
	 */
	public function testResolvesEveryReferencedSubjectInTwoLookups(): void {
		$spyPresenter = $this->getSpyPresenter();
		// Registered in reverse, because InMemorySubjectLookup returns its own order rather than the
		// requested one, exactly as the graph-backed lookups do. Reading the batch out as a map
		// instead of by the requested ids therefore fails the order assertion below.
		$subjectLookup = new InMemorySubjectLookup(
			$this->newSubjectReferencing( 's11111111111aa1', 's11111111111aa2', 's11111111111aa3' ),
			TestSubject::build( id: 's11111111111aa3', label: new SubjectLabel( 'third target' ) ),
			TestSubject::build( id: 's11111111111aa2', label: new SubjectLabel( 'second target' ) ),
			TestSubject::build( id: 's11111111111aa1', label: new SubjectLabel( 'first target' ) ),
		);
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( 's11111111111maa' ), new PageIdentifiers( new PageId( 42 ), 'Requested', 0 ) ],
			[ new SubjectId( 's11111111111aa1' ), new PageIdentifiers( new PageId( 101 ), 'First', 0 ) ],
			[ new SubjectId( 's11111111111aa2' ), new PageIdentifiers( new PageId( 102 ), 'Second', 0 ) ],
			[ new SubjectId( 's11111111111aa3' ), new PageIdentifiers( new PageId( 103 ), 'Third', 0 ) ],
		] );

		$query = $this->newQuery(
			$spyPresenter,
			$subjectLookup,
			pageIdentifiersLookup: $pageIdentifiersLookup
		);

		$query->execute(
			subjectId: 's11111111111maa',
			includePageIdentifiers: true,
			includeReferencedSubjects: true
		);

		$this->assertSame( 1, $subjectLookup->getSubjectsCallCount );
		$this->assertSame( 1, $pageIdentifiersLookup->getPageIdsOfSubjectsCallCount );

		// One each, for the requested Subject; the three targets add none.
		$this->assertSame( 1, $subjectLookup->getSubjectCallCount );
		$this->assertSame( 1, $pageIdentifiersLookup->getPageIdOfSubjectCallCount );

		// Requested Subject first, then the targets in the order the Statements reach them, not the
		// order the lookup returned them in.
		$this->assertSame(
			[ 's11111111111maa', 's11111111111aa1', 's11111111111aa2', 's11111111111aa3' ],
			array_keys( $spyPresenter->response->subjects )
		);

		// Each target keeps its own hosting page: one shared entry would serve the wrong page here.
		$this->assertSame( 101, $spyPresenter->response->subjects['s11111111111aa1']->pageId );
		$this->assertSame( 102, $spyPresenter->response->subjects['s11111111111aa2']->pageId );
		$this->assertSame( 103, $spyPresenter->response->subjects['s11111111111aa3']->pageId );
	}

	public function testOmitsReferencedSubjectMissingFromTheBatch(): void {
		$spyPresenter = $this->getSpyPresenter();
		$subjectLookup = new InMemorySubjectLookup(
			$this->newSubjectReferencing( 's11111111111aa1', 's11111111111aa2' ),
			TestSubject::build( id: 's11111111111aa2', label: new SubjectLabel( 'second target' ) ),
		);

		$query = $this->newQuery(
			$spyPresenter,
			$subjectLookup,
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111aa2' ), new PageIdentifiers( new PageId( 102 ), 'Second', 0 ) ],
			] )
		);

		$query->execute(
			subjectId: 's11111111111maa',
			includePageIdentifiers: true,
			includeReferencedSubjects: true
		);

		$this->assertSame(
			[ 's11111111111maa', 's11111111111aa2' ],
			array_keys( $spyPresenter->response->subjects )
		);
		$this->assertSame( 1, $subjectLookup->getSubjectsCallCount );
	}

	/**
	 * A target whose page the caller may not read is omitted, and batching the resolution does not
	 * hand it out: the check stays per Subject, against that Subject's own hosting page.
	 */
	public function testOmitsReferencedSubjectOnUnreadablePage(): void {
		$spyPresenter = $this->getSpyPresenter();

		$query = $this->newQuery(
			$spyPresenter,
			new InMemorySubjectLookup(
				$this->newSubjectReferencing( 's11111111111aa1', 's11111111111aa2' ),
				TestSubject::build( id: 's11111111111aa1', label: new SubjectLabel( 'hidden target' ) ),
				TestSubject::build( id: 's11111111111aa2', label: new SubjectLabel( 'visible target' ) ),
			),
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111maa' ), new PageIdentifiers( new PageId( 42 ), 'Requested', 0 ) ],
				[ new SubjectId( 's11111111111aa1' ), new PageIdentifiers( new PageId( 101 ), 'Hidden', 0 ) ],
				[ new SubjectId( 's11111111111aa2' ), new PageIdentifiers( new PageId( 102 ), 'Visible', 0 ) ],
			] ),
			readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 101 ] )
		);

		$query->execute(
			subjectId: 's11111111111maa',
			includePageIdentifiers: true,
			includeReferencedSubjects: true
		);

		$this->assertSame(
			[ 's11111111111maa', 's11111111111aa2' ],
			array_keys( $spyPresenter->response->subjects )
		);
	}

	/**
	 * The counterpart of GetPageSubjectsQuery's rule, and the reason the batch read takes
	 * `?? null` rather than indexing the map directly: a referenced Subject the lookup resolved but
	 * whose hosting page it cannot place is still served, so Subjects on a readable old revision do
	 * not disappear once the Subject is deleted. See pageIsReadableOrUnresolved.
	 */
	public function testServesReferencedSubjectWhoseHostingPageDoesNotResolve(): void {
		$spyPresenter = $this->getSpyPresenter();

		$query = $this->newQuery(
			$spyPresenter,
			new InMemorySubjectLookup(
				$this->newSubjectReferencing( 's11111111111aa1' ),
				TestSubject::build( id: 's11111111111aa1', label: new SubjectLabel( 'unplaced target' ) ),
			),
			// Places the requested Subject only, so the target's entry is absent from the batch.
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111maa' ), new PageIdentifiers( new PageId( 42 ), 'Requested', 0 ) ],
			] )
		);

		$query->execute(
			subjectId: 's11111111111maa',
			includePageIdentifiers: true,
			includeReferencedSubjects: true
		);

		$this->assertSame(
			[ 's11111111111maa', 's11111111111aa1' ],
			array_keys( $spyPresenter->response->subjects )
		);
		$this->assertNull( $spyPresenter->response->subjects['s11111111111aa1']->pageId );
	}

	/**
	 * A denied request costs nothing beyond resolving the Subject it was denied: batching must not
	 * pull the targets in before the gate, on behalf of a caller that gets a not-found (#1046).
	 */
	public function testDeniedRequestResolvesNoReferencedSubjects(): void {
		$spyPresenter = $this->getSpyPresenter();
		$subjectLookup = new InMemorySubjectLookup(
			$this->newSubjectReferencing( 's11111111111aa1', 's11111111111aa2' ),
			TestSubject::build( id: 's11111111111aa1' ),
			TestSubject::build( id: 's11111111111aa2' ),
		);
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( 's11111111111maa' ), new PageIdentifiers( new PageId( 42 ), 'Requested', 0 ) ],
			[ new SubjectId( 's11111111111aa1' ), new PageIdentifiers( new PageId( 101 ), 'First', 0 ) ],
			[ new SubjectId( 's11111111111aa2' ), new PageIdentifiers( new PageId( 102 ), 'Second', 0 ) ],
		] );

		$query = $this->newQuery(
			$spyPresenter,
			$subjectLookup,
			pageIdentifiersLookup: $pageIdentifiersLookup,
			readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 42 ] )
		);

		$query->execute(
			subjectId: 's11111111111maa',
			includePageIdentifiers: true,
			includeReferencedSubjects: true
		);

		$this->assertTrue( $spyPresenter->notFound );
		$this->assertSame( 0, $subjectLookup->getSubjectsCallCount );
		$this->assertSame( 0, $pageIdentifiersLookup->getPageIdsOfSubjectsCallCount );
	}

	public function testExpandingRelationsEmbedsWhatTheDependentSubjectsPointAtInTurn(): void {
		$response = $this->expandedResponseFor(
			$this->subjectOf( self::PERSON, 'Person', [ 'Birth' => self::BIRTH ] ),
			$this->subjectOf( self::BIRTH, 'Birth', [ 'Place' => self::PLACE, 'Record' => self::RECORD ] ),
			$this->subjectOf( self::PLACE, 'Place', [] ),
			$this->subjectOf( self::RECORD, 'Record', [ 'Archive' => self::ARCHIVE ] ),
			$this->subjectOf( self::ARCHIVE, 'Place', [] ),
		);

		$this->assertSame(
			[ self::PERSON, self::BIRTH, self::PLACE, self::RECORD, self::ARCHIVE ],
			array_keys( $response->subjects )
		);
	}

	public function testExpandingRelationsLeavesOutWhatAStandaloneTargetPointsAt(): void {
		$response = $this->expandedResponseFor(
			$this->subjectOf( self::PERSON, 'Person', [ 'Friend' => self::FRIEND ] ),
			$this->subjectOf( self::FRIEND, 'Person', [ 'Birth' => self::BIRTH ] ),
			$this->subjectOf( self::BIRTH, 'Birth', [] ),
		);

		$this->assertSame( [ self::PERSON, self::FRIEND ], array_keys( $response->subjects ) );
	}

	public function testExpandingRelationsEmbedsDependentSubjectsThatPointAtEachOtherOnce(): void {
		$response = $this->expandedResponseFor(
			$this->subjectOf( self::PERSON, 'Person', [ 'Birth' => self::BIRTH ] ),
			$this->subjectOf( self::BIRTH, 'Birth', [ 'Record' => self::RECORD ] ),
			$this->subjectOf( self::RECORD, 'Record', [ 'Birth' => self::BIRTH ] ),
		);

		$this->assertSame( [ self::PERSON, self::BIRTH, self::RECORD ], array_keys( $response->subjects ) );
	}

	/**
	 * Every Subject on one page, with Births and Records as the Dependent Subjects.
	 */
	private function expandedResponseFor( Subject $requested, Subject ...$others ): GetSubjectResponse {
		$spyPresenter = $this->getSpyPresenter();
		$onePage = new PageIdentifiers( new PageId( 42 ), 'Pablo Picasso', 0 );

		$query = $this->newQuery(
			$spyPresenter,
			new InMemorySubjectLookup( $requested, ...$others ),
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( array_map(
				static fn ( Subject $subject ): array => [ $subject->id, $onePage ],
				[ $requested, ...$others ]
			) ),
			schemaLookup: new InMemorySchemaLookup(
				TestSchema::build( name: 'Person' ),
				TestSchema::build( name: 'Birth', subjectsAreDependent: true ),
				TestSchema::build( name: 'Record', subjectsAreDependent: true ),
				TestSchema::build( name: 'Place' ),
			)
		);

		$query->execute(
			subjectId: $requested->id->text,
			includePageIdentifiers: false,
			includeReferencedSubjects: true
		);

		return $spyPresenter->response;
	}

	/**
	 * @param array<string, string> $targetsByProperty
	 */
	private function subjectOf( string $id, string $schema, array $targetsByProperty ): Subject {
		$statements = [];

		foreach ( $targetsByProperty as $property => $targetId ) {
			$statements[] = TestStatement::buildRelation( $property, [ TestRelation::build( targetId: $targetId ) ] );
		}

		return TestSubject::build(
			id: $id,
			label: null,
			schemaName: TestSchema::reference( $schema ),
			statements: new StatementList( $statements )
		);
	}

	private function newQuery(
		GetSubjectPresenter $presenter,
		SubjectLookup $subjectLookup,
		InMemoryPageIdentifiersLookup $pageIdentifiersLookup = new InMemoryPageIdentifiersLookup(),
		?SubjectResponseItemFactory $responseItemFactory = null,
		PageReadAuthorizer $readAuthorizer = new StubPageReadAuthorizer( allowed: true ),
		SchemaLookup $schemaLookup = new InMemorySchemaLookup(),
	): GetSubjectQuery {
		return new GetSubjectQuery(
			$presenter,
			$subjectLookup,
			$pageIdentifiersLookup,
			$responseItemFactory ?? $this->responseItemFactory(),
			$readAuthorizer,
			TestSubjectIds::newParser(),
			TestNaming::newDependentSubjectRules( $schemaLookup ),
		);
	}

	/**
	 * For the tests that do not care which Subject a page calls its Main one: every page it is asked
	 * about answers that it has none.
	 */
	private function responseItemFactory(): SubjectResponseItemFactory {
		return TestNaming::newResponseItemFactory( new InMemorySubjectRepository() );
	}

	private function responseItemFactoryWithMainSubject(
		Subject $mainSubject,
		SubjectMap $otherSubjects,
		int $pageId
	): SubjectResponseItemFactory {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects( new PageSubjects( $mainSubject, $otherSubjects ), new PageId( $pageId ) );

		return TestNaming::newResponseItemFactory( $repository );
	}

	private function newSubjectReferencing( string ...$targetIds ): Subject {
		$statements = [];

		foreach ( $targetIds as $index => $targetId ) {
			$statements[] = TestStatement::build(
				'FriendOf ' . $index,
				new RelationValue( TestRelation::build( id: 'r111111111111a' . ( $index + 1 ), targetId: $targetId ) ),
				RelationType::NAME,
			);
		}

		return TestSubject::build(
			id: 's11111111111maa',
			label: new SubjectLabel( 'requested subject' ),
			statements: new StatementList( $statements )
		);
	}

}
