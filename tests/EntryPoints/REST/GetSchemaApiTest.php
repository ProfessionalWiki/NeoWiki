<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaApi;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaApi
 * @group database
 */
class GetSchemaApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	private SchemaId $schemaId;
	private CsrfValidator $csrfValidatorStub;
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
			"format": "relation",
			"relation": "MyRelation",
			"targetSchema": "TestSchema",
			"description": "This is a test schema"
		}
	}
}
JSON
		);

		$this->csrfValidatorStub = $this->createStub( CsrfValidator::class );
		$this->csrfValidatorStub->method( 'verifyCsrfToken' )->willReturn( true );
	}

	public function testSchemaIsFound(): void {
		$response = $this->executeHandler(
			new GetSchemaApi( $this->csrfValidatorStub ),
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
                "type": "relation",
                "description": "This is a test schema",
                "required": false,
                "default": null,
                "multiple": false,
                "format": "relation",
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
			new GetSchemaApi( $this->csrfValidatorStub ),
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
