<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectPresenter
 * @group database
 */
class GetSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	private readonly CsrfValidator $csrfValidatorstub;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema(
			'GetSubjectApiTestSchema',
			<<<JSON
{
	"title": "GetSubjectApiTestSchema",
	"propertyDefinitions": {
		"MyRelation": {
			"type": "relation",
			"format": "relation",
			"relation": "MyRelation",
			"targetSchema": "GetSubjectApiTestSchema"
		}
	}
}
JSON
		);

		$this->csrfValidatorstub = $this->createStub( CsrfValidator::class );
		$this->csrfValidatorstub->method( 'verifyCsrfToken' )->willReturn( true );
	}

	public function testSubjectIsFound(): void {
		$this->createPageWithSubjects(
			'GetSubjectApiTest',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440000',
				label: new SubjectLabel( 'Test subject 426655440000' ),
				schemaId: new SchemaId( 'GetSubjectApiTestSchema' )
			)
		);

		$response = $this->executeHandler(
			new GetSubjectApi( csrfValidator: $this->csrfValidatorstub ),
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
            "properties": []
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
			new GetSubjectApi( csrfValidator: $this->csrfValidatorstub ),
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
		$this->createSchema(
			'GetSubjectApiTestSchema',
			<<<JSON
{
	"title": "GetSubjectApiTestSchema",
	"propertyDefinitions": {
		"MyRelation": {
			"type": "relation",
			"format": "relation",
			"relation": "MyRelation",
			"targetSchema": "GetSubjectApiTestSchema"
		}
	}
}
JSON
		);

		$firstPageId = $this->createPageWithSubjects(
			'GetSubjectApiTest0000',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440000',
				schemaId: new SchemaId( 'GetSubjectApiTestSchema' ),
				properties: new StatementList( [
					'MyRelation' => [
						[
							'id' => '00000000-1111-2222-1100-000000440011',
							'target' => '123e4567-e89b-12d3-a456-426655440001',
						],
						[
							'id' => '00000000-1111-2222-1100-000000440022',
							'target' => '123e4567-e89b-12d3-a456-426655440002',
						],
					],
				] )
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: '123e4567-e89b-12d3-a456-426655440001',
					schemaId: new SchemaId( 'GetSubjectApiTestSchema' ),
				)
			)
		)->getPage()->getId();

		$secondPageId = $this->createPageWithSubjects(
			'GetSubjectApiTest0002',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440002',
				schemaId: new SchemaId( 'GetSubjectApiTestSchema' ),
				properties: new StatementList( [
					'MyRelation' => [
						[
							'id' => '00000000-1111-2222-1100-000000440033',
							'target' => '123e4567-e89b-12d3-a456-426655440001',
						],
					],
				] )
			),
		)->getPage()->getId();

		$response = $this->executeHandler(
			new GetSubjectApi( csrfValidator: $this->csrfValidatorstub ),
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
            "properties": {
                "MyRelation": [
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
        },
        "123e4567-e89b-12d3-a456-426655440001": {
            "id": "123e4567-e89b-12d3-a456-426655440001",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $firstPageId,
            "pageTitle": "GetSubjectApiTest0000",
            "properties": []
        },
        "123e4567-e89b-12d3-a456-426655440002": {
            "id": "123e4567-e89b-12d3-a456-426655440002",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $secondPageId,
            "pageTitle": "GetSubjectApiTest0002",
            "properties": {
                "MyRelation": [
                    {
                    	"id": "00000000-1111-2222-1100-000000440033",
                        "target": "123e4567-e89b-12d3-a456-426655440001"
                    }
                ]
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
