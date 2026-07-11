<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPersistenceDeserializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPersistenceDeserializer
 */
class MappingPersistenceDeserializerTest extends TestCase {

	private function deserialize( string $json ): \ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping {
		return ( new MappingPersistenceDeserializer() )->deserialize( new MappingName( 'Person to EDM' ), $json );
	}

	public function testDeserializesTheSchemaTargetAndSubjectClass(): void {
		$mapping = $this->deserialize( $this->validJson() );

		$this->assertSame( 'Person to EDM', $mapping->name->getText() );
		$this->assertSame( 'Person', $mapping->schema->getText() );
		$this->assertSame( 'edm', $mapping->target );
		$this->assertSame( 'edm:ProvidedCHO', $mapping->subjectClass );
	}

	public function testDeserializesThePrefixes(): void {
		$this->assertSame(
			[
				'edm' => 'http://www.europeana.eu/schemas/edm/',
				'dc' => 'http://purl.org/dc/elements/1.1/',
			],
			$this->deserialize( $this->validJson() )->prefixes
		);
	}

	public function testDeserializesAPropertyWithALanguageTag(): void {
		$name = $this->deserialize( $this->validJson() )->properties->get( 'Name' );

		$this->assertNotNull( $name );
		$this->assertSame( 'dc:title', $name->predicate );
		$this->assertSame( 'en', $name->language );
		$this->assertNull( $name->datatype );
	}

	public function testDeserializesAPropertyWithADatatypeOverride(): void {
		$born = $this->deserialize( $this->validJson() )->properties->get( 'BirthYear' );

		$this->assertNotNull( $born );
		$this->assertSame( 'dc:date', $born->predicate );
		$this->assertNull( $born->language );
		$this->assertSame( 'edm:year', $born->datatype );
	}

	public function testThrowsWhenTheSchemaIsMissing(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->deserialize( '{ "version": 1, "target": "edm", "subject": { "class": "edm:X" }, "properties": {} }' );
	}

	public function testThrowsOnInvalidJson(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->deserialize( 'not json' );
	}

	private function validJson(): string {
		return <<<JSON
			{
				"version": 1,
				"schema": "Person",
				"target": "edm",
				"prefixes": {
					"edm": "http://www.europeana.eu/schemas/edm/",
					"dc": "http://purl.org/dc/elements/1.1/"
				},
				"subject": { "class": "edm:ProvidedCHO" },
				"properties": {
					"Name": { "predicate": "dc:title", "lang": "en" },
					"BirthYear": { "predicate": "dc:date", "datatype": "edm:year" }
				}
			}
			JSON;
	}

}
