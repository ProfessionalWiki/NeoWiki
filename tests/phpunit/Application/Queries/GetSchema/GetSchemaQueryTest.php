<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetSchema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaQuery;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaQuery
 */
class GetSchemaQueryTest extends TestCase {

	private InMemorySchemaLookup $schemaLookup;
	private GetSchemaPresenterSpy $presenter;

	protected function setUp(): void {
		$this->schemaLookup = new InMemorySchemaLookup();
		$this->presenter = new GetSchemaPresenterSpy();
	}

	public function testExecuteWhenSchemaNotFound(): void {
		$this->newGetSchemaQuery()->execute( 'doesNotExist' );

		$this->assertTrue( $this->presenter->notFound );
	}

	private function newGetSchemaQuery(): GetSchemaQuery {
		return new GetSchemaQuery(
			$this->presenter,
			$this->schemaLookup,
			new SchemaPresentationSerializer()
		);
	}

	public function testExecuteWithCompleteSchema(): void {
		$schemaName = 'testName';

		$schema = TestSchema::build(
			name: new SchemaName( $schemaName ),
			description: 'test',
			properties: new PropertyDefinitions( [
				'property1' => TestProperty::buildText(
					description: 'A string property',
					required: false,
					default: null,
					multiple: false
				)
			] )
		);

		$this->schemaLookup->updateSchema( $schema );

		$this->newGetSchemaQuery()->execute( $schemaName );

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "description": "test",
    "propertyDefinitions": {
        "property1": {
            "type": "text",
            "description": "A string property",
            "required": false,
            "default": null,
            "multiple": false,
            "uniqueItems": false
        }
    }
}
JSON
			,
			$this->presenter->schemaJson
		);
	}

}
