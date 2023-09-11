<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectPresenter
 * @group database
 */
class GetSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema(
			'GetSubjectApiTestSchema',
			<<<JSON
{
	"title": "GetSubjectApiTestSchema",
	"propertyDefinitions": {
		"MyRelation": {
			"format": "relation",
			"relation": "MyRelation",
			"targetSchema": "GetSubjectApiTestSchema"
		}
	}
}
JSON
		);
	}

	public function testSubjectIsFound(): void {
		$this->createPageWithSubjects(
			'GetSubjectApiTest',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440000',
				label: new SubjectLabel( 'Test subject 426655440000' ),
				schemaId: new SchemaName( 'GetSubjectApiTestSchema' )
			)
		);

		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'subjectId' => '123e4567-e89b-12d3-a456-426655440000'
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "requestedId": "123e4567-e89b-12d3-a456-426655440000",
    "subjects": {
        "123e4567-e89b-12d3-a456-426655440000": {
            "id": "123e4567-e89b-12d3-a456-426655440000",
            "label": "Test subject 426655440000",
            "schema": "GetSubjectApiTestSchema",
            "statements": []
        }
    }
}
JSON,
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testSubjectIsNotFound(): void {
		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'subjectId' => '404e4567-e89b-12d3-a456-426655440404'
				]
			] )
		);

		$this->assertSame(
			'{"subject":null}',
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testFullExpansion(): void {
		$firstPageId = $this->createPageWithSubjects(
			'GetSubjectApiTest0000',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440000',
				schemaId: new SchemaName( 'GetSubjectApiTestSchema' ),
				statements: new StatementList( [
					TestStatement::buildRelation(
						'MyRelation',
						[
							TestRelation::build( id: '00000000-1111-2222-1100-000000440011', targetId: '123e4567-e89b-12d3-a456-426655440001' ),
							TestRelation::build( id: '00000000-1111-2222-1100-000000440022', targetId: '123e4567-e89b-12d3-a456-426655440002' ),
						]
					)
				] )
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: '123e4567-e89b-12d3-a456-426655440001',
					schemaId: new SchemaName( 'GetSubjectApiTestSchema' ),
				)
			)
		)->getPage()->getId();

		$secondPageId = $this->createPageWithSubjects(
			'GetSubjectApiTest0002',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440002',
				schemaId: new SchemaName( 'GetSubjectApiTestSchema' ),
				statements: new StatementList( [
					TestStatement::buildRelation(
						'MyRelation',
						[
							TestRelation::build( id: '00000000-1111-2222-1100-000000440033', targetId: '123e4567-e89b-12d3-a456-426655440001' ),
						]
					)
				] )
			),
		)->getPage()->getId();

		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'subjectId' => '123e4567-e89b-12d3-a456-426655440000'
				],
				'queryParams' => [
					'expand' => 'page|relations'
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "requestedId": "123e4567-e89b-12d3-a456-426655440000",
    "subjects": {
        "123e4567-e89b-12d3-a456-426655440000": {
            "id": "123e4567-e89b-12d3-a456-426655440000",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $firstPageId,
            "pageTitle": "GetSubjectApiTest0000",
            "statements": {
                "MyRelation": {
                	"format": "relation",
                	"value": [
						{
							"id": "00000000-1111-2222-1100-000000440011",
							"target": "123e4567-e89b-12d3-a456-426655440001"
						},
						{
							"id": "00000000-1111-2222-1100-000000440022",
							"target": "123e4567-e89b-12d3-a456-426655440002"
						}
					]
                }
            }
        },
        "123e4567-e89b-12d3-a456-426655440001": {
            "id": "123e4567-e89b-12d3-a456-426655440001",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $firstPageId,
            "pageTitle": "GetSubjectApiTest0000",
            "statements": []
        },
        "123e4567-e89b-12d3-a456-426655440002": {
            "id": "123e4567-e89b-12d3-a456-426655440002",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $secondPageId,
            "pageTitle": "GetSubjectApiTest0002",
            "statements": {
                "MyRelation": {
                "format": "relation",
				"value": [
						{
							"id": "00000000-1111-2222-1100-000000440033",
							"target": "123e4567-e89b-12d3-a456-426655440001"
						}
					]
                }
            }
        }
    }
}
JSON,
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

}
