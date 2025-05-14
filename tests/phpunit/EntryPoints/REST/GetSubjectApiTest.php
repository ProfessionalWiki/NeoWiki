<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\GetSubjectApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\GetSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Presentation\RestGetSubjectPresenter
 * @group Database
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
			"type": "relation",
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
				id: 'sTestGSA1111114',
				label: new SubjectLabel( 'Test subject sTestGSA1111114' ),
				schemaId: new SchemaName( 'GetSubjectApiTestSchema' )
			)
		);

		$response = $this->executeHandler(
			new GetSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'subjectId' => 'sTestGSA1111114'
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "requestedId": "sTestGSA1111114",
    "subjects": {
        "sTestGSA1111114": {
            "id": "sTestGSA1111114",
            "label": "Test subject sTestGSA1111114",
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
					'subjectId' => 'sTestGSA1111115'
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
				id: 'sTestGSA1111111',
				schemaId: new SchemaName( 'GetSubjectApiTestSchema' ),
				statements: new StatementList( [
					TestStatement::buildRelation(
						'MyRelation',
						[
							TestRelation::build( id: 'rTestGSA1111rr1', targetId: 'sTestGSA1111112' ),
							TestRelation::build( id: 'rTestGSA1111rr2', targetId: 'sTestGSA1111113' ),
						]
					)
				] )
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestGSA1111112',
					schemaId: new SchemaName( 'GetSubjectApiTestSchema' ),
				)
			)
		)->getPage()->getId();

		$secondPageId = $this->createPageWithSubjects(
			'GetSubjectApiTest0002',
			mainSubject: TestSubject::build(
				id: 'sTestGSA1111113',
				schemaId: new SchemaName( 'GetSubjectApiTestSchema' ),
				statements: new StatementList( [
					TestStatement::buildRelation(
						'MyRelation',
						[
							TestRelation::build( id: 'rTestGSA1111rr3', targetId: 'sTestGSA1111112' ),
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
					'subjectId' => 'sTestGSA1111111'
				],
				'queryParams' => [
					'expand' => 'page|relations'
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "requestedId": "sTestGSA1111111",
    "subjects": {
        "sTestGSA1111111": {
            "id": "sTestGSA1111111",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $firstPageId,
            "pageTitle": "GetSubjectApiTest0000",
            "statements": {
                "MyRelation": {
                	"type": "relation",
                	"value": [
						{
							"id": "rTestGSA1111rr1",
							"target": "sTestGSA1111112"
						},
						{
							"id": "rTestGSA1111rr2",
							"target": "sTestGSA1111113"
						}
					]
                }
            }
        },
        "sTestGSA1111112": {
            "id": "sTestGSA1111112",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $firstPageId,
            "pageTitle": "GetSubjectApiTest0000",
            "statements": []
        },
        "sTestGSA1111113": {
            "id": "sTestGSA1111113",
            "label": "Test subject",
            "schema": "GetSubjectApiTestSchema",
            "pageId": $secondPageId,
            "pageTitle": "GetSubjectApiTest0002",
            "statements": {
                "MyRelation": {
                "type": "relation",
				"value": [
						{
							"id": "rTestGSA1111rr3",
							"target": "sTestGSA1111112"
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
