<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetSubject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationTypeId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
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
			)
		);

		$query->execute( '00000000-6666-0000-0000-000000000001' );

		$this->assertEquals(
			new GetSubjectResponse(
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
			new InMemorySubjectLookup()
		);

		$query->execute( TestSubject::ZERO_GUID );

		$this->assertTrue( $spyPresenter->notFound );
	}

}
