<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
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
	}

	public function testSubjectIsFound(): void {
		$this->createPageWithSubjects(
			'GetSubjectApiTest',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440000',
				label: new SubjectLabel( 'Test subject 426655440000' ),
				schemaId: new SchemaId( 'GetSubjectApiTest' )
			)
		);

		$response = $this->executeHandler(
			NeoWikiExtension::newGetSubjectApi(),
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
            "schema": "GetSubjectApiTest",
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
			NeoWikiExtension::newGetSubjectApi(),
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
			"type": "array",
			"items": {
				"type": "relation",
				"format": "relation",
				"targetSchema": "GetSubjectApiTestSchema"
			}
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
				relations: new RelationList( [
					TestRelation::build( type: 'MyRelation', targetId: '123e4567-e89b-12d3-a456-426655440001' ),
					TestRelation::build( type: 'MyRelation', targetId: '123e4567-e89b-12d3-a456-426655440002' ),
				] )
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: '123e4567-e89b-12d3-a456-426655440001',
				)
			)
		)->getPage()->getId();

		$secondPageId = $this->createPageWithSubjects(
			'GetSubjectApiTest0002',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440002',
				schemaId: new SchemaId( 'GetSubjectApiTestSchema' ),
				relations: new RelationList( [
					TestRelation::build( type: 'MyRelation', targetId: '123e4567-e89b-12d3-a456-426655440001' )
				] )
			),
		)->getPage()->getId();

		$response = $this->executeHandler(
			NeoWikiExtension::newGetSubjectApi(),
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
                        "target": "123e4567-e89b-12d3-a456-426655440001",
                        "properties": []
                    },
                    {
                        "target": "123e4567-e89b-12d3-a456-426655440002",
                        "properties": []
                    }
                ]
            }
        },
        "123e4567-e89b-12d3-a456-426655440001": {
            "id": "123e4567-e89b-12d3-a456-426655440001",
            "label": "Test subject",
            "schema": "TestSubjectSchemaId",
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
                        "target": "123e4567-e89b-12d3-a456-426655440001",
                        "properties": []
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
