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
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\RelationFormat;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

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
					id: '00000000-6666-0000-0000-000000000001',
					label: new SubjectLabel( 'expected label' ),
					schemaId: new SchemaName( '00000000-6666-0000-0000-000000000010' ),
					statements: new StatementList( [
						TestStatement::build( 'expected property 1', 'expected value 1' ),
						TestStatement::build( 'expected property 2', value: new NumberValue( 2 ), format: 'number' ),
						TestStatement::build(
							'FriendOf',
							new RelationValue( TestRelation::build(
								id: '00000000-1111-2222-1100-000000000020',
								targetId: '00000000-6666-0000-0000-000000000020',
								properties: [ 'relation property' => 'relation value' ]
							) ),
							RelationFormat::NAME,
						),
					] ),
				),
			),
			new InMemoryPageIdentifiersLookup(),
		);

		$query->execute(
			subjectId: '00000000-6666-0000-0000-000000000001',
			includePageIdentifiers: false,
			includeReferencedSubjects: false
		);

		$this->assertEquals(
			new GetSubjectResponse(
				'00000000-6666-0000-0000-000000000001',
				[
					'00000000-6666-0000-0000-000000000001' => new GetSubjectResponseItem(
						id: '00000000-6666-0000-0000-000000000001',
						label: 'expected label',
						schemaId: '00000000-6666-0000-0000-000000000010',
						statements: [
							'expected property 1' => [
								'format' => 'text',
								'value' => [ 'expected value 1' ]
							],
							'expected property 2' => [
								'format' => 'number',
								'value' => 2
							],
							'FriendOf' => [
								'format' => 'relation',
								'value' => [
									[
										'id' => '00000000-1111-2222-1100-000000000020',
										'target' => '00000000-6666-0000-0000-000000000020',
										'properties' => [
											'relation property' => 'relation value'
										],
									]
								]
							]
						],
						pageId: null,
						pageTitle: null,
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
				[ new SubjectId( TestSubject::ZERO_GUID ), new PageIdentifiers( new PageId( 1 ), 'wrong title' ) ],
				[ $subject->id, new PageIdentifiers( new PageId( 42 ), 'right title' ) ],
			] ),
		);

		$query->execute(
			subjectId: $subject->getId()->text,
			includePageIdentifiers: true,
			includeReferencedSubjects: false
		);

		$response = $spyPresenter->response;

		$this->assertSame( 42, $response->subjects[$response->requestedId]->pageId );
		$this->assertSame( 'right title', $response->subjects[$response->requestedId]->pageTitle );
	}

	public function testIncludeReferencedSubjects(): void {
		$spyPresenter = $this->getSpyPresenter();
		$schemaId = new SchemaName( 'GetSubjectQueryTest' );

		$subject = TestSubject::build(
			id: '00000000-6666-0000-0000-000000000008',
			label: new SubjectLabel( 'requested subject' ),
			schemaId: $schemaId,
			statements: new StatementList( [
				TestStatement::build(
					'FriendOf',
					new RelationValue( TestRelation::build(
						id: '00000000-6666-0000-0000-000000000007',
						targetId: '00000000-6666-0000-0000-000000000009',
						properties: [ 'relation property' => 'relation value' ]
					) ),
					RelationFormat::NAME,
				),
			] )
		);

		$referencedSubject = TestSubject::build(
			id: '00000000-6666-0000-0000-000000000009',
			label: new SubjectLabel( 'referenced subject' ),
		);

		$query = new GetSubjectQuery(
			$spyPresenter,
			new InMemorySubjectLookup( $subject, $referencedSubject ),
			new InMemoryPageIdentifiersLookup( [
				[ $subject->id, new PageIdentifiers( new PageId( 42 ), 'subject title' ) ],
				[ $referencedSubject->id, new PageIdentifiers( new PageId( 1337 ), 'referenced title' ) ],
			] ),
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
	}

}
