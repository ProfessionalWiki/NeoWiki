<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPersistenceDeserializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPersistenceDeserializer
 */
class MappingPersistenceDeserializerTest extends TestCase {

	private function deserialize( string $json ): Mapping {
		return ( new MappingPersistenceDeserializer() )->deserialize( new MappingName( 'EDM' ), $json );
	}

	public function testDeserializesTheNameAndPageLevelPrefixes(): void {
		$mapping = $this->deserialize( $this->validJson() );

		$this->assertSame( 'EDM', $mapping->name->getText() );
		$this->assertSame(
			[
				'edm' => 'http://www.europeana.eu/schemas/edm/',
				'dc' => 'http://purl.org/dc/elements/1.1/',
			],
			$mapping->prefixes
		);
	}

	public function testDeserializesEverySchemaEntrySubjectClass(): void {
		$mapping = $this->deserialize( $this->validJson() );

		$this->assertSame( 'edm:ProvidedCHO', $mapping->forSchema( new SchemaName( 'Person' ) )?->subjectClass );
		$this->assertSame( 'edm:Place', $mapping->forSchema( new SchemaName( 'City' ) )?->subjectClass );
	}

	public function testUnmappedSchemaHasNoEntry(): void {
		$this->assertNull( $this->deserialize( $this->validJson() )->forSchema( new SchemaName( 'Artwork' ) ) );
	}

	public function testSkipsAMalformedSchemaEntryButKeepsItsValidSiblings(): void {
		// "Broken" has no subject.class — a shape only an import can store, since save validation rejects
		// it. It is skipped while the valid entries before and after it deserialize, mirroring the
		// per-property skip, so one bad entry never sinks the whole page's projection.
		$mapping = $this->deserialize( <<<JSON
			{
				"version": 1,
				"prefixes": { "edm": "http://www.europeana.eu/schemas/edm/" },
				"schemas": {
					"Person": { "subject": { "class": "edm:Agent" }, "properties": {} },
					"Broken": { "properties": {} },
					"City": { "subject": { "class": "edm:Place" }, "properties": {} }
				}
			}
			JSON );

		$this->assertSame( 'edm:Agent', $mapping->forSchema( new SchemaName( 'Person' ) )?->subjectClass );
		$this->assertSame( 'edm:Place', $mapping->forSchema( new SchemaName( 'City' ) )?->subjectClass );
		$this->assertNull(
			$mapping->forSchema( new SchemaName( 'Broken' ) ),
			'the entry missing subject.class is skipped, not included'
		);
	}

	public function testSkipsAnEntryWithAReservedSchemaKeyButKeepsItsValidSiblings(): void {
		// "page" is a reserved Schema name, so no Subject can carry it and the entry is unreachable. Save
		// validation permits the key (it never constructs a SchemaName), so a saved page can hold one; the
		// deserializer validates the key by constructing a SchemaName and skips the entry when that throws,
		// keeping its valid siblings rather than letting one dead entry sink the whole page's mapping.
		$mapping = $this->deserialize( <<<JSON
			{
				"version": 1,
				"prefixes": { "edm": "http://www.europeana.eu/schemas/edm/" },
				"schemas": {
					"Person": { "subject": { "class": "edm:Agent" }, "properties": {} },
					"page": { "subject": { "class": "edm:Place" }, "properties": {} },
					"City": { "subject": { "class": "edm:Place" }, "properties": {} }
				}
			}
			JSON );

		$this->assertSame( 'edm:Agent', $mapping->forSchema( new SchemaName( 'Person' ) )?->subjectClass );
		$this->assertSame( 'edm:Place', $mapping->forSchema( new SchemaName( 'City' ) )?->subjectClass );
	}

	public function testDeserializesAPropertyWithALanguageTag(): void {
		$name = $this->deserialize( $this->validJson() )->forSchema( new SchemaName( 'Person' ) )?->properties->get( 'Name' );

		$this->assertNotNull( $name );
		$this->assertSame( 'dc:title', $name->predicate );
		$this->assertSame( 'en', $name->language );
		$this->assertNull( $name->datatype );
	}

	public function testDeserializesAPropertyWithADatatypeOverride(): void {
		$born = $this->deserialize( $this->validJson() )->forSchema( new SchemaName( 'Person' ) )?->properties->get( 'BirthYear' );

		$this->assertNotNull( $born );
		$this->assertSame( 'dc:date', $born->predicate );
		$this->assertNull( $born->language );
		$this->assertSame( 'edm:year', $born->datatype );
	}

	public function testThrowsWhenTheSchemasKeyIsMissing(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->deserialize( '{ "version": 1, "prefixes": {} }' );
	}

	public function testThrowsOnInvalidJson(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->deserialize( 'not json' );
	}

	private function validJson(): string {
		return <<<JSON
			{
				"version": 1,
				"prefixes": {
					"edm": "http://www.europeana.eu/schemas/edm/",
					"dc": "http://purl.org/dc/elements/1.1/"
				},
				"schemas": {
					"Person": {
						"subject": { "class": "edm:ProvidedCHO" },
						"properties": {
							"Name": { "predicate": "dc:title", "lang": "en" },
							"BirthYear": { "predicate": "dc:date", "datatype": "edm:year" }
						}
					},
					"City": {
						"subject": { "class": "edm:Place" },
						"properties": {}
					}
				}
			}
			JSON;
	}

}
