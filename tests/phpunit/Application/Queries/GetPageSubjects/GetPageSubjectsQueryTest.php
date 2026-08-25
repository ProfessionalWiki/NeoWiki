<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetPageSubjects;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsResponse;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
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

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsQuery
 */
class GetPageSubjectsQueryTest extends TestCase {

	public function testReturnsMainAndChildSubjects(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build(
					id: 's11111111111maa',
					label: new SubjectLabel( 'main label' ),
					schemaName: new SchemaName( 'TestSchema' ),
					statements: new StatementList( [
						TestStatement::build( 'name', 'Berlin' ),
					] )
				),
				new SubjectMap(
					TestSubject::build(
						id: 's11111111111ca2',
						label: new SubjectLabel( 'child two' ),
					),
					TestSubject::build(
						id: 's11111111111ca3',
						label: new SubjectLabel( 'child three' ),
					),
					TestSubject::build(
						id: 's11111111111ca1',
						label: new SubjectLabel( 'child one' ),
					),
				)
			),
			new PageId( 42 )
		);

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository )->execute( 42 );

		$this->assertSame(
			[ 's11111111111maa', 's11111111111ca2', 's11111111111ca3', 's11111111111ca1' ],
			array_keys( $presenter->response->subjects )
		);
		$this->assertEquals(
			new GetPageSubjectsResponse(
				pageId: 42,
				mainSubjectId: 's11111111111maa',
				subjects: [
					's11111111111maa' => new GetSubjectResponseItem(
						id: 's11111111111maa',
						label: 'main label',
						displayName: 'main label',
						schema: 'TestSchema',
						statements: [
							'name' => [
								'propertyType' => 'text',
								'value' => [ 'Berlin' ]
							],
						],
						pageId: null,
						pageTitle: null,
						pageNamespaceId: null,
					),
					's11111111111ca2' => new GetSubjectResponseItem(
						id: 's11111111111ca2',
						label: 'child two',
						displayName: 'child two',
						schema: TestSubject::DEFAULT_SCHEMA_ID,
						statements: [],
						pageId: null,
						pageTitle: null,
						pageNamespaceId: null,
					),
					's11111111111ca3' => new GetSubjectResponseItem(
						id: 's11111111111ca3',
						label: 'child three',
						displayName: 'child three',
						schema: TestSubject::DEFAULT_SCHEMA_ID,
						statements: [],
						pageId: null,
						pageTitle: null,
						pageNamespaceId: null,
					),
					's11111111111ca1' => new GetSubjectResponseItem(
						id: 's11111111111ca1',
						label: 'child one',
						displayName: 'child one',
						schema: TestSubject::DEFAULT_SCHEMA_ID,
						statements: [],
						pageId: null,
						pageTitle: null,
						pageNamespaceId: null,
					),
				]
			),
			$presenter->response
		);
	}

	public function testReturnsEmptyResponseForPageWithoutSubjects(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, new InMemorySubjectRepository() )->execute( 99 );

		$this->assertEquals(
			new GetPageSubjectsResponse( pageId: 99, mainSubjectId: null, subjects: [] ),
			$presenter->response
		);
	}

	public function testReturnsChildrenOnlyWhenNoMainSubject(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				null,
				new SubjectMap(
					TestSubject::build( id: 's11111111111oa1', label: new SubjectLabel( 'lone child' ) ),
				)
			),
			new PageId( 7 )
		);

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository )->execute( 7 );

		$this->assertNull( $presenter->response->mainSubjectId );
		$this->assertSame( [ 's11111111111oa1' ], array_keys( $presenter->response->subjects ) );
	}

	/**
	 * The page name names its Main Subject and would misname every other Subject on the page, so a
	 * label-less Child falls through to its Schema instead.
	 */
	public function testLabellessSubjectsAreNamedAfterThePageAndTheSchema(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build( id: 's11111111111maa', label: null, schemaName: new SchemaName( 'Museum' ) ),
				new SubjectMap(
					TestSubject::build( id: 's11111111111ca1', label: null, schemaName: new SchemaName( 'Attendance' ) ),
				)
			),
			new PageId( 42 )
		);

		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$repository,
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111maa' ), new PageIdentifiers( new PageId( 42 ), 'Rijksmuseum', 0 ) ],
				[ new SubjectId( 's11111111111ca1' ), new PageIdentifiers( new PageId( 42 ), 'Rijksmuseum', 0 ) ],
			] )
		)->execute( 42 );

		$this->assertNull( $presenter->response->subjects['s11111111111maa']->label );
		$this->assertSame( 'Rijksmuseum', $presenter->response->subjects['s11111111111maa']->displayName );

		$this->assertNull( $presenter->response->subjects['s11111111111ca1']->label );
		$this->assertSame( 'Attendance', $presenter->response->subjects['s11111111111ca1']->displayName );
	}

	public function testIncludesSchemasWhenRequested(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build(
					id: 's11111111111maa',
					schemaName: new SchemaName( 'CitySchema' ),
				),
				new SubjectMap(
					TestSubject::build( id: 's11111111111ca1', schemaName: new SchemaName( 'PopulationSchema' ) ),
					TestSubject::build( id: 's11111111111ca2', schemaName: new SchemaName( 'PopulationSchema' ) ),
				)
			),
			new PageId( 42 )
		);

		$schemaLookup = new InMemorySchemaLookup(
			TestSchema::build( name: new SchemaName( 'CitySchema' ) ),
			TestSchema::build( name: new SchemaName( 'PopulationSchema' ) ),
		);

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository, schemaLookup: $schemaLookup )->execute( 42, includeSchemas: true );

		$this->assertNotNull( $presenter->response->schemas );
		$this->assertSame(
			[ 'CitySchema', 'PopulationSchema' ],
			array_keys( $presenter->response->schemas )
		);
	}

	public function testIncludesReferencedSubjectsForRelationValues(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build(
					id: 's11111111111maa',
					statements: new StatementList( [
						TestStatement::build(
							'partner',
							new RelationValue( TestRelation::build( id: 'r11111111111maa', targetId: 's11111111111tar' ) ),
							RelationType::NAME,
						),
					] )
				),
				new SubjectMap()
			),
			new PageId( 42 )
		);

		$referenced = TestSubject::build( id: 's11111111111tar', label: new SubjectLabel( 'target subject' ) );
		$subjectLookup = new InMemorySubjectLookup( $referenced );
		// The gate omits referenced Subjects whose page does not resolve, so the lookup must
		// honor the production invariant: a returned Subject always has a resolvable page.
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ $referenced->id, new PageIdentifiers( new PageId( 137 ), 'Target Page', 0 ) ],
		] );

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository, subjectLookup: $subjectLookup, pageIdentifiersLookup: $pageIdentifiersLookup )
			->execute( 42, includeReferencedSubjects: true );

		$this->assertNotNull( $presenter->response->referencedSubjects );
		$this->assertArrayHasKey( 's11111111111tar', $presenter->response->referencedSubjects );
		$this->assertSame( 'target subject', $presenter->response->referencedSubjects['s11111111111tar']->label );
	}

	public function testReferencedSubjectIsIncludedOnlyOnceWhenMultipleStatementsReferenceIt(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build(
					id: 's11111111111maa',
					statements: new StatementList( [
						TestStatement::build(
							'partner',
							new RelationValue( TestRelation::build( id: 'r11111111111maa', targetId: 's11111111111tar' ) ),
							RelationType::NAME,
						),
					] )
				),
				new SubjectMap(
					TestSubject::build(
						id: 's11111111111ca1',
						statements: new StatementList( [
							TestStatement::build(
								'sibling',
								new RelationValue( TestRelation::build( id: 'r11111111111ca1', targetId: 's11111111111tar' ) ),
								RelationType::NAME,
							),
						] )
					),
				)
			),
			new PageId( 42 )
		);

		$referenced = TestSubject::build( id: 's11111111111tar', label: new SubjectLabel( 'shared target' ) );
		$subjectLookup = new InMemorySubjectLookup( $referenced );
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ $referenced->id, new PageIdentifiers( new PageId( 137 ), 'Target Page', 0 ) ],
		] );

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository, subjectLookup: $subjectLookup, pageIdentifiersLookup: $pageIdentifiersLookup )
			->execute( 42, includeReferencedSubjects: true );

		$this->assertSame(
			[ 's11111111111tar' ],
			array_keys( $presenter->response->referencedSubjects )
		);
	}

	public function testReferencedSubjectsCarryPageIdentifiers(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build(
					id: 's11111111111maa',
					statements: new StatementList( [
						TestStatement::build(
							'partner',
							new RelationValue( TestRelation::build( id: 'r11111111111maa', targetId: 's11111111111tar' ) ),
							RelationType::NAME,
						),
					] )
				),
				new SubjectMap()
			),
			new PageId( 42 )
		);

		$referenced = TestSubject::build( id: 's11111111111tar' );
		$subjectLookup = new InMemorySubjectLookup( $referenced );
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ $referenced->id, new PageIdentifiers( new PageId( 137 ), 'Target Page', 12 ) ],
		] );

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository, subjectLookup: $subjectLookup, pageIdentifiersLookup: $pageIdentifiersLookup )
			->execute( 42, includeReferencedSubjects: true );

		$this->assertSame( 137, $presenter->response->referencedSubjects['s11111111111tar']->pageId );
		$this->assertSame( 'Target Page', $presenter->response->referencedSubjects['s11111111111tar']->pageTitle );
		$this->assertSame( 12, $presenter->response->referencedSubjects['s11111111111tar']->pageNamespaceId );
	}

	/**
	 * A target lives on a page of its own, so its main-ness is asked of that page rather than
	 * inherited from the page being read.
	 */
	public function testLabellessReferencedSubjectIsNamedAfterItsOwnPage(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build(
					id: 's11111111111maa',
					statements: new StatementList( [
						TestStatement::build(
							'partner',
							new RelationValue( TestRelation::build( id: 'r11111111111maa', targetId: 's11111111111tar' ) ),
							RelationType::NAME,
						),
					] )
				),
				new SubjectMap()
			),
			new PageId( 42 )
		);

		$referenced = TestSubject::build( id: 's11111111111tar', label: null );
		$repository->savePageSubjects( new PageSubjects( $referenced, new SubjectMap() ), new PageId( 137 ) );

		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$repository,
			subjectLookup: new InMemorySubjectLookup( $referenced ),
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ $referenced->id, new PageIdentifiers( new PageId( 137 ), 'Target Page', 0 ) ],
			] )
		)->execute( 42, includeReferencedSubjects: true );

		$this->assertNull( $presenter->response->referencedSubjects['s11111111111tar']->label );
		$this->assertSame(
			'Target Page',
			$presenter->response->referencedSubjects['s11111111111tar']->displayName
		);
	}

	public function testReferencedSubjectsAndSchemasAreNullWhenNotRequested(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, new InMemorySubjectRepository() )->execute( 42 );

		$this->assertNull( $presenter->response->referencedSubjects );
		$this->assertNull( $presenter->response->schemas );
	}

	/**
	 * The page's own Subjects come out of one page's content, so learning where they live is one
	 * lookup however many there are. Per-id resolution must stay at zero: doing it inside the loop
	 * reinstates a round trip per Subject on the page with the batch call still at one.
	 */
	public function testResolvesHostingPagesOfPageSubjectsInOneLookup(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build( id: 's11111111111maa' ),
				new SubjectMap(
					TestSubject::build( id: 's11111111111ca1' ),
					TestSubject::build( id: 's11111111111ca2' ),
				)
			),
			new PageId( 42 )
		);

		// An unrebuilt or stale graph can answer differently for two Subjects one page carries, so
		// each item takes its own entry. Reusing one entry for all three would pass on shared values.
		// The 'Stale' assertions pin that per-item resolution, not the disclosure they imply: this
		// query never authorizes the page a Subject resolves to. Tracked in #1252.
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( 's11111111111maa' ), new PageIdentifiers( new PageId( 42 ), 'Current', 0 ) ],
			[ new SubjectId( 's11111111111ca1' ), new PageIdentifiers( new PageId( 7 ), 'Stale', 12 ) ],
		] );

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository, pageIdentifiersLookup: $pageIdentifiersLookup )->execute( 42 );

		$this->assertSame( 1, $pageIdentifiersLookup->getPageIdsOfSubjectsCallCount );
		$this->assertSame( 0, $pageIdentifiersLookup->getPageIdOfSubjectCallCount );

		$this->assertSame( 42, $presenter->response->subjects['s11111111111maa']->pageId );
		$this->assertSame( 7, $presenter->response->subjects['s11111111111ca1']->pageId );
		$this->assertSame( 'Stale', $presenter->response->subjects['s11111111111ca1']->pageTitle );
		// A Subject the graph does not place carries no page.
		$this->assertNull( $presenter->response->subjects['s11111111111ca2']->pageId );
	}

	/**
	 * Two lookups for every referenced Subject the page reaches, on top of the one the page's own
	 * Subjects cost, rather than two apiece. The per-id counts stay at zero for both lookups.
	 */
	public function testResolvesEveryReferencedSubjectInTwoLookups(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				$this->newSubjectReferencing( 's11111111111maa', 's11111111111tt1', 's11111111111tt2' ),
				new SubjectMap(
					$this->newSubjectReferencing( 's11111111111ca1', 's11111111111tt3' ),
				)
			),
			new PageId( 42 )
		);

		// Registered in reverse, because InMemorySubjectLookup returns its own order rather than the
		// requested one, exactly as the graph-backed lookups do. Reading the batch out as a map
		// instead of by the collected ids therefore fails the order assertion below.
		$subjectLookup = new InMemorySubjectLookup(
			TestSubject::build( id: 's11111111111tt3' ),
			TestSubject::build( id: 's11111111111tt2' ),
			TestSubject::build( id: 's11111111111tt1' ),
		);
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( 's11111111111tt1' ), new PageIdentifiers( new PageId( 101 ), 'First', 0 ) ],
			[ new SubjectId( 's11111111111tt2' ), new PageIdentifiers( new PageId( 102 ), 'Second', 0 ) ],
			[ new SubjectId( 's11111111111tt3' ), new PageIdentifiers( new PageId( 103 ), 'Third', 0 ) ],
		] );

		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$repository,
			subjectLookup: $subjectLookup,
			pageIdentifiersLookup: $pageIdentifiersLookup
		)->execute( 42, includeReferencedSubjects: true );

		$this->assertSame( 1, $subjectLookup->getSubjectsCallCount );
		$this->assertSame( 0, $subjectLookup->getSubjectCallCount );

		// One for the page's own Subjects, one for the referenced ones.
		$this->assertSame( 2, $pageIdentifiersLookup->getPageIdsOfSubjectsCallCount );
		$this->assertSame( 0, $pageIdentifiersLookup->getPageIdOfSubjectCallCount );

		// In the order the page's Statements reach the targets, not the order the lookup returned
		// them in, and each with its own hosting page.
		$this->assertSame(
			[ 's11111111111tt1', 's11111111111tt2', 's11111111111tt3' ],
			array_keys( $presenter->response->referencedSubjects )
		);
		$this->assertSame( 101, $presenter->response->referencedSubjects['s11111111111tt1']->pageId );
		$this->assertSame( 102, $presenter->response->referencedSubjects['s11111111111tt2']->pageId );
		$this->assertSame( 103, $presenter->response->referencedSubjects['s11111111111tt3']->pageId );
	}

	/**
	 * The counterpart of GetSubjectQuery's rule, which serves such a Subject. Here it is omitted
	 * rather than served ungated, so the `?? null` on the batch read must keep feeding the
	 * null-page branch.
	 */
	public function testOmitsReferencedSubjectWhoseHostingPageDoesNotResolve(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				$this->newSubjectReferencing( 's11111111111maa', 's11111111111tt1', 's11111111111tt2' ),
				new SubjectMap()
			),
			new PageId( 42 )
		);

		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$repository,
			subjectLookup: new InMemorySubjectLookup(
				TestSubject::build( id: 's11111111111tt1' ),
				TestSubject::build( id: 's11111111111tt2' ),
			),
			// Places tt2 only, so tt1 resolves to a Subject the query cannot place.
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111tt2' ), new PageIdentifiers( new PageId( 102 ), 'Second', 0 ) ],
			] )
		)->execute( 42, includeReferencedSubjects: true );

		$this->assertSame(
			[ 's11111111111tt2' ],
			array_keys( $presenter->response->referencedSubjects )
		);
	}

	/**
	 * A referenced Subject the page already carries is left out by the collected-id filter alone.
	 * The target is given a hosting page and a resolvable Subject here, so dropping that filter
	 * puts it in the response rather than tripping the null-page branch on the way.
	 */
	public function testReferencedSubjectOnThePageIsExcludedByTheCollectedIdFilter(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				$this->newSubjectReferencing( 's11111111111maa', 's11111111111ca1' ),
				new SubjectMap( TestSubject::build( id: 's11111111111ca1' ) )
			),
			new PageId( 42 )
		);

		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$repository,
			subjectLookup: new InMemorySubjectLookup( TestSubject::build( id: 's11111111111ca1' ) ),
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( 's11111111111maa' ), new PageIdentifiers( new PageId( 42 ), 'Page', 0 ) ],
				[ new SubjectId( 's11111111111ca1' ), new PageIdentifiers( new PageId( 42 ), 'Page', 0 ) ],
			] )
		)->execute( 42, includeReferencedSubjects: true );

		$this->assertSame( [], $presenter->response->referencedSubjects );
	}

	/**
	 * The counterpart of GetSubjectQueryTest::testDeniedRequestResolvesNoReferencedSubjects. A
	 * denied page yields no Subjects to reach targets from, so both batch calls go out empty and
	 * cost nothing - but only because DatabasePageIdentifiersLookup and PointInTimeSubjectLookup guard
	 * the empty list. Asserting zero lookups here pins that rather than leaving it to those guards.
	 */
	public function testDeniedPageResolvesNoReferencedSubjects(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				$this->newSubjectReferencing( 's11111111111maa', 's11111111111tt1' ),
				new SubjectMap()
			),
			new PageId( 42 )
		);

		$subjectLookup = new InMemorySubjectLookup( TestSubject::build( id: 's11111111111tt1' ) );
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( 's11111111111tt1' ), new PageIdentifiers( new PageId( 101 ), 'Target', 0 ) ],
		] );

		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$repository,
			subjectLookup: $subjectLookup,
			pageIdentifiersLookup: $pageIdentifiersLookup,
			readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 42 ] )
		)->execute( 42, includeReferencedSubjects: true );

		$this->assertSame( [], $presenter->response->subjects );
		$this->assertSame( [], $presenter->response->referencedSubjects );
		$this->assertSame( 0, $subjectLookup->getSubjectCallCount );
		$this->assertSame( 0, $pageIdentifiersLookup->getPageIdOfSubjectCallCount );
	}

	private function newSubjectReferencing( string $id, string ...$targetIds ): Subject {
		$statements = [];

		foreach ( $targetIds as $index => $targetId ) {
			$statements[] = TestStatement::build(
				'reaches ' . $index,
				new RelationValue( TestRelation::build(
					id: 'r1111111111' . substr( $id, -3 ) . ( $index + 1 ),
					targetId: $targetId
				) ),
				RelationType::NAME,
			);
		}

		return TestSubject::build( id: $id, statements: new StatementList( $statements ) );
	}

	private function newQuery(
		object $presenter,
		InMemorySubjectRepository $repository,
		?SubjectLookup $subjectLookup = null,
		?SchemaLookup $schemaLookup = null,
		?PageIdentifiersLookup $pageIdentifiersLookup = null,
		?PageReadAuthorizer $readAuthorizer = null,
	): GetPageSubjectsQuery {
		return new GetPageSubjectsQuery(
			presenter: $presenter,
			subjectRepository: $repository,
			subjectLookup: $subjectLookup ?? new InMemorySubjectLookup(),
			schemaLookup: $schemaLookup ?? new InMemorySchemaLookup(),
			schemaSerializer: new SchemaPresentationSerializer(),
			pageIdentifiersLookup: $pageIdentifiersLookup ?? new InMemoryPageIdentifiersLookup(),
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
		);
	}

	private function newSpyPresenter(): object {
		return new class() implements GetPageSubjectsPresenter {

			public GetPageSubjectsResponse $response;

			public function presentPageSubjects( GetPageSubjectsResponse $response ): void {
				$this->response = $response;
			}

		};
	}

}
