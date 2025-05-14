<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\GetSchemaApi;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\GetSchemaApi
 * @group Database
 */
class GetSchemaApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	private SchemaName $schemaId;
	private const SCHEMA_ID = 'TestSchema';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( self::SCHEMA_ID, <<<JSON
{
	"title": "TestSchema",
	"description": "This is a test schema",
	"propertyDefinitions": {
		"MyRelation": {
			"type": "relation",
			"relation": "MyRelation",
			"targetSchema": "TestSchema",
			"description": "This is a test schema"
		}
	}
}
JSON
		);
	}

	public function testSchemaIsFound(): void {
		$response = $this->executeHandler(
			new GetSchemaApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'schemaName' => self::SCHEMA_ID
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "schema": {
        "description": "This is a test schema",
        "propertyDefinitions": {
            "MyRelation": {
                "description": "This is a test schema",
                "required": false,
                "default": null,
                "multiple": false,
                "type": "relation",
                "relation": "MyRelation",
                "targetSchema": "TestSchema"
            }
        }
    }
}
JSON,
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testSchemaIsNotFound(): void {
		$response = $this->executeHandler(
			new GetSchemaApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'schemaName' => 'NonExistentSchema'
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			'{"schema":null}',
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

}
