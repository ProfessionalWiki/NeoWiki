<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetSubject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery
 */
class GetSubjectQueryTest extends TestCase {

	public function testPresentsSubjectInHappyPathResponse(): void {
		$spyPresenter = $this->getSpyPresenter();

		$query = new GetSubjectQuery(
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
								properties: [ 'relation property' => 'relation value' ]
							) ),
							RelationType::NAME,
						),
					] ),
				),
			),
			new InMemoryPageIdentifiersLookup(),
			new StubPageReadAuthorizer( allowed: true ),
			TestSubjectIds::newParser(),
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
										'properties' => [
											'relation property' => 'relation value'
										],
									]
								]
							]
						],
						pageId: null,
						pageTitle: null,
						pageNamespaceId: null,
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

		$query = new GetSubjectQuery(
			$spyPresenter,
			new InMemorySubjectLookup(),
			new InMemoryPageIdentifiersLookup(),
			new StubPageReadAuthorizer( allowed: true ),
			TestSubjectIds::newParser(),
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

		$query = new GetSubjectQuery(
			$spyPresenter,
			new InMemorySubjectLookup( $subject ),
			new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( TestSubject::ZERO_GUID ), new PageIdentifiers( new PageId( 1 ), 'wrong title', 0 ) ],
				[ $subject->id, new PageIdentifiers( new PageId( 42 ), 'right title', 12 ) ],
			] ),
			new StubPageReadAuthorizer( allowed: true ),
			TestSubjectIds::newParser(),
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
						properties: [ 'relation property' => 'relation value' ]
					) ),
					RelationType::NAME,
				),
			] )
		);

		$referencedSubject = TestSubject::build(
			id: 's11111111111133',
			label: new SubjectLabel( 'referenced subject' ),
		);

		$query = new GetSubjectQuery(
			$spyPresenter,
			new InMemorySubjectLookup( $subject, $referencedSubject ),
			new InMemoryPageIdentifiersLookup( [
				[ $subject->id, new PageIdentifiers( new PageId( 42 ), 'subject title', 0 ) ],
				[ $referencedSubject->id, new PageIdentifiers( new PageId( 1337 ), 'referenced title', 12 ) ],
			] ),
			new StubPageReadAuthorizer( allowed: true ),
			TestSubjectIds::newParser(),
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

		$query = new GetSubjectQuery(
			$spyPresenter,
			$subjectLookup,
			$pageIdentifiersLookup,
			new StubPageReadAuthorizer( allowed: true ),
			TestSubjectIds::newParser(),
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

		$query = new GetSubjectQuery(
			$spyPresenter,
			$subjectLookup,
			new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111aa2' ), new PageIdentifiers( new PageId( 102 ), 'Second', 0 ) ],
			] ),
			new StubPageReadAuthorizer( allowed: true ),
			TestSubjectIds::newParser(),
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

		$query = new GetSubjectQuery(
			$spyPresenter,
			new InMemorySubjectLookup(
				$this->newSubjectReferencing( 's11111111111aa1', 's11111111111aa2' ),
				TestSubject::build( id: 's11111111111aa1', label: new SubjectLabel( 'hidden target' ) ),
				TestSubject::build( id: 's11111111111aa2', label: new SubjectLabel( 'visible target' ) ),
			),
			new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111maa' ), new PageIdentifiers( new PageId( 42 ), 'Requested', 0 ) ],
				[ new SubjectId( 's11111111111aa1' ), new PageIdentifiers( new PageId( 101 ), 'Hidden', 0 ) ],
				[ new SubjectId( 's11111111111aa2' ), new PageIdentifiers( new PageId( 102 ), 'Visible', 0 ) ],
			] ),
			new SelectivePageReadAuthorizer( deniedPageIds: [ 101 ] ),
			TestSubjectIds::newParser(),
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

		$query = new GetSubjectQuery(
			$spyPresenter,
			new InMemorySubjectLookup(
				$this->newSubjectReferencing( 's11111111111aa1' ),
				TestSubject::build( id: 's11111111111aa1', label: new SubjectLabel( 'unplaced target' ) ),
			),
			// Places the requested Subject only, so the target's entry is absent from the batch.
			new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111maa' ), new PageIdentifiers( new PageId( 42 ), 'Requested', 0 ) ],
			] ),
			new StubPageReadAuthorizer( allowed: true ),
			TestSubjectIds::newParser(),
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

		$query = new GetSubjectQuery(
			$spyPresenter,
			$subjectLookup,
			$pageIdentifiersLookup,
			new SelectivePageReadAuthorizer( deniedPageIds: [ 42 ] ),
			TestSubjectIds::newParser(),
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
