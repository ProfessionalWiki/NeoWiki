<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetPageSubjects;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsResponse;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
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
						id: 's11111111111ca1',
						label: new SubjectLabel( 'child one' ),
					),
					TestSubject::build(
						id: 's11111111111ca2',
						label: new SubjectLabel( 'child two' ),
					),
				)
			),
			new PageId( 42 )
		);

		$presenter = $this->newSpyPresenter();

		( new GetPageSubjectsQuery( $presenter, $repository ) )->execute( 42 );

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
					's11111111111ca1' => new GetSubjectResponseItem(
						id: 's11111111111ca1',
						label: 'child one',
						schemaName: TestSubject::DEFAULT_SCHEMA_ID,
						statements: [],
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
				]
			),
			$presenter->response
		);
	}

	public function testReturnsEmptyResponseForPageWithoutSubjects(): void {
		$presenter = $this->newSpyPresenter();

		( new GetPageSubjectsQuery( $presenter, new InMemorySubjectRepository() ) )->execute( 99 );

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

		( new GetPageSubjectsQuery( $presenter, $repository ) )->execute( 7 );

		$this->assertNull( $presenter->response->mainSubjectId );
		$this->assertSame( [ 's11111111111oa1' ], array_keys( $presenter->response->subjects ) );
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
