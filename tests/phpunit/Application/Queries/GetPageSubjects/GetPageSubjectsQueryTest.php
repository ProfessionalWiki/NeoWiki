<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetPageSubjects;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsResponse;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
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
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
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
						schemaName: 'TestSchema',
						statements: [
							'name' => [
								'type' => 'text',
								'value' => [ 'Berlin' ]
							],
						],
						pageId: null,
						pageTitle: null,
					),
					's11111111111ca2' => new GetSubjectResponseItem(
						id: 's11111111111ca2',
						label: 'child two',
						schemaName: TestSubject::DEFAULT_SCHEMA_ID,
						statements: [],
						pageId: null,
						pageTitle: null,
					),
					's11111111111ca3' => new GetSubjectResponseItem(
						id: 's11111111111ca3',
						label: 'child three',
						schemaName: TestSubject::DEFAULT_SCHEMA_ID,
						statements: [],
						pageId: null,
						pageTitle: null,
					),
					's11111111111ca1' => new GetSubjectResponseItem(
						id: 's11111111111ca1',
						label: 'child one',
						schemaName: TestSubject::DEFAULT_SCHEMA_ID,
						statements: [],
						pageId: null,
						pageTitle: null,
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

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository, subjectLookup: $subjectLookup )->execute( 42, includeReferencedSubjects: true );

		$this->assertNotNull( $presenter->response->referencedSubjects );
		$this->assertArrayHasKey( 's11111111111tar', $presenter->response->referencedSubjects );
		$this->assertSame( 'target subject', $presenter->response->referencedSubjects['s11111111111tar']->label );
	}

	public function testReferencedSubjectsAlreadyOnPageAreNotDuplicated(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build(
					id: 's11111111111maa',
					statements: new StatementList( [
						TestStatement::build(
							'partner',
							new RelationValue( TestRelation::build( id: 'r11111111111maa', targetId: 's11111111111ca1' ) ),
							RelationType::NAME,
						),
					] )
				),
				new SubjectMap(
					TestSubject::build( id: 's11111111111ca1', label: new SubjectLabel( 'on-page target' ) ),
				)
			),
			new PageId( 42 )
		);

		$subjectLookup = new InMemorySubjectLookup(
			TestSubject::build( id: 's11111111111ca1', label: new SubjectLabel( 'on-page target' ) )
		);

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository, subjectLookup: $subjectLookup )->execute( 42, includeReferencedSubjects: true );

		$this->assertSame( [], $presenter->response->referencedSubjects );
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

		$subjectLookup = new InMemorySubjectLookup(
			TestSubject::build( id: 's11111111111tar', label: new SubjectLabel( 'shared target' ) )
		);

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository, subjectLookup: $subjectLookup )->execute( 42, includeReferencedSubjects: true );

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
			[ $referenced->id, new PageIdentifiers( new PageId( 137 ), 'Target Page' ) ],
		] );

		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, $repository, subjectLookup: $subjectLookup, pageIdentifiersLookup: $pageIdentifiersLookup )
			->execute( 42, includeReferencedSubjects: true );

		$this->assertSame( 137, $presenter->response->referencedSubjects['s11111111111tar']->pageId );
		$this->assertSame( 'Target Page', $presenter->response->referencedSubjects['s11111111111tar']->pageTitle );
	}

	public function testReferencedSubjectsAndSchemasAreNullWhenNotRequested(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery( $presenter, new InMemorySubjectRepository() )->execute( 42 );

		$this->assertNull( $presenter->response->referencedSubjects );
		$this->assertNull( $presenter->response->schemas );
	}

	private function newQuery(
		object $presenter,
		InMemorySubjectRepository $repository,
		?SubjectLookup $subjectLookup = null,
		?SchemaLookup $schemaLookup = null,
		?PageIdentifiersLookup $pageIdentifiersLookup = null,
	): GetPageSubjectsQuery {
		return new GetPageSubjectsQuery(
			presenter: $presenter,
			subjectRepository: $repository,
			subjectLookup: $subjectLookup ?? new InMemorySubjectLookup(),
			schemaLookup: $schemaLookup ?? new InMemorySchemaLookup(),
			schemaSerializer: new SchemaPresentationSerializer(),
			pageIdentifiersLookup: $pageIdentifiersLookup ?? new InMemoryPageIdentifiersLookup(),
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
