<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetSchema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaQuery;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\TextFormat;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

class GetSchemaQueryTest extends TestCase {
	private InMemorySchemaLookup $schemaLookup;

	protected function setUp(): void {
		$this->schemaLookup = new InMemorySchemaLookup();
	}

	public function testExecuteWhenSchemaNotFound(): void {
		$schemaName = 'testId';

		$presenter = $this->getSpyPresenter();
		$serializer = new SchemaSerializer();

		$getSchemaQuery = new GetSchemaQuery( $presenter, $this->schemaLookup, $serializer );

		$getSchemaQuery->execute( $schemaName );

		$this->assertTrue( $presenter->notFound );
	}

	public function testExecuteWithCompleteSchema(): void {
		$schemaName = 'testName';

		$schema = TestSchema::build(
			name: new SchemaName( $schemaName ),
			description: 'test',
			properties: new PropertyDefinitions( [
				'property1' => new StringProperty(
					TextFormat::NAME,
					'A string property',
					false,
					null,
					false
				)
			] )
		);

		$this->schemaLookup->updateSchema( $schema );
		$presenter = $this->getSpyPresenter();
		$serializer = new SchemaSerializer();

		$getSchemaQuery = new GetSchemaQuery( $presenter, $this->schemaLookup, $serializer );

		$getSchemaQuery->execute( $schemaName );

		$expectedSchemaJson = <<<JSON
{
    "description": "test",
    "propertyDefinitions": {
        "property1": {
            "type": "string",
            "description": "A string property",
            "required": false,
            "default": null,
            "multiple": false,
            "format": "text"
        }
    }
}
JSON;

		$this->assertJsonStringEqualsJsonString( $expectedSchemaJson, $presenter->schemaJson );
	}

	private function getSpyPresenter(): GetSchemaPresenter {
		return new class implements GetSchemaPresenter {
			public string $schemaJson = '';
			public bool $notFound = false;

			public function presentSchema( string $json ): void {
				$this->schemaJson = $json;
			}

			public function presentSchemaNotFound(): void {
				$this->notFound = true;
			}
		};
	}

}
