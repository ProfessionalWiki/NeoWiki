<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\ReleaseContent;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer
 */
class ReleasedSchemasTest extends TestCase {

	use ReadsReleaseContent;

	/**
	 * @dataProvider schemaProvider
	 */
	public function testReadsTheSchemaAsStored( string $release, string $schemaName ): void {
		$stored = self::decodedReleaseContent( $release, 'Schema', $schemaName );
		$schema = $this->readSchema( $release, $schemaName );

		$this->assertSame(
			[
				'description' => $stored['description'] ?? '',
				'subjectsAreDependent' => $stored['subjectsAreDependent'] ?? false,
				'propertyNames' => array_keys( $stored['propertyDefinitions'] ),
			],
			[
				'description' => $schema->getDescription(),
				'subjectsAreDependent' => $schema->subjectsAreDependent(),
				'propertyNames' => array_keys( $schema->getAllProperties()->asMap() ),
			]
		);
	}

	public static function schemaProvider(): iterable {
		return self::releaseContentPages( 'Schema' );
	}

	/**
	 * @dataProvider propertyProvider
	 */
	public function testReadsEveryPropertyDefinitionAsStored( string $release, string $schemaName, string $propertyName ): void {
		$property = $this->readSchema( $release, $schemaName )->getProperty( $propertyName );
		$stored = self::decodedReleaseContent( $release, 'Schema', $schemaName )['propertyDefinitions'][$propertyName];

		$this->assertNotInstanceOf( UnregisteredTypeProperty::class, $property );
		$this->assertEquals(
			$this->inCanonicalForm( $stored ),
			array_intersect_key( $property->toJson(), $stored )
		);
	}

	public static function propertyProvider(): iterable {
		foreach ( self::releaseContentPages( 'Schema' ) as [ $release, $schemaName ] ) {
			$properties = self::decodedReleaseContent( $release, 'Schema', $schemaName )['propertyDefinitions'];

			foreach ( array_keys( $properties ) as $propertyName ) {
				yield "$release $schemaName: $propertyName" => [ $release, $schemaName, (string)$propertyName ];
			}
		}
	}

	private function readSchema( string $release, string $schemaName ): Schema {
		return NeoWikiExtension::getInstance()->getPersistenceSchemaDeserializer()->deserialize(
			new SchemaName( $schemaName ),
			self::releaseContent( $release, 'Schema', $schemaName )
		);
	}

	/**
	 * The canonical form writes a Constraint with the default severity as its bare value.
	 */
	private function inCanonicalForm( array $property ): array {
		return array_map(
			static fn ( mixed $value ): mixed => is_array( $value ) && ( $value['severity'] ?? null ) === 'warning'
				? $value['value'] ?? true
				: $value,
			$property
		);
	}

}
