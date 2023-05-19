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
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationTypeId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectProperties;
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
					schemaId: new SchemaId( '00000000-6666-0000-0000-000000000010' ),
					relations: new RelationList( [
						new Relation(
							type: new RelationTypeId( 'FriendOf' ),
							targetId: new SubjectId( '00000000-6666-0000-0000-000000000020' ),
							properties: new RelationProperties( [
								'relation property' => 'relation value'
							] ),
						)
					] ),
					properties: new SubjectProperties( [
						'expected property 1' => 'expected value 1',
						'expected property 2' => 'expected value 2',
					] ),
				),
			),
			new InMemoryPageIdentifiersLookup()
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
						properties: [
							'expected property 1' => 'expected value 1',
							'expected property 2' => 'expected value 2',
							'FriendOf' => [
								[
									'target' => '00000000-6666-0000-0000-000000000020',
									'properties' => [
										'relation property' => 'relation value'
									],
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
			new InMemoryPageIdentifiersLookup()
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
	}

	public function testIncludeReferencedSubjects(): void {
		$spyPresenter = $this->getSpyPresenter();

		$subject = TestSubject::build(
			id: '00000000-6666-0000-0000-000000000008',
			label: new SubjectLabel( 'requested subject' ),
			relations: new RelationList( [
				new Relation(
					type: new RelationTypeId( 'FriendOf' ),
					targetId: new SubjectId( '00000000-6666-0000-0000-000000000009' ),
					properties: new RelationProperties( [
						'relation property' => 'relation value'
					] ),
				)
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
	}

}
