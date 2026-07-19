<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingContentValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingContentValidator
 */
class MappingContentValidatorTest extends TestCase {

	private function assertValid( string $json ): void {
		$validator = MappingContentValidator::newInstance();
		$this->assertTrue( $validator->validate( $json ), 'Expected valid, got: ' . implode( '; ', $validator->getErrors() ) );
		$this->assertSame( [], $validator->getErrors() );
	}

	private function assertInvalidAt( string $json, string $expectedErrorPointer ): void {
		$validator = MappingContentValidator::newInstance();
		$this->assertFalse( $validator->validate( $json ) );
		$this->assertArrayHasKey( $expectedErrorPointer, $validator->getErrors() );
	}

	public function testAcceptsAValidMapping(): void {
		$this->assertValid( $this->validMapping() );
	}

	public function testAcceptsAMappingUsingAnAbsoluteIriPredicate(): void {
		$this->assertValid( <<<JSON
			{
				"version": 1,
				"schemas": {
					"Person": {
						"subject": { "class": "http://example.org/ns/Person" },
						"properties": {
							"Name": { "predicate": "http://example.org/ns/name" }
						}
					}
				}
			}
			JSON );
	}

	public function testRejectsAMissingSchemasKey(): void {
		$this->assertInvalidAt(
			<<<JSON
			{ "version": 1, "prefixes": { "edm": "http://europeana.eu/edm/" } }
			JSON,
			'/'
		);
	}

	public function testRejectsAMissingSubjectClass(): void {
		$this->assertInvalidAt(
			<<<JSON
			{ "version": 1, "schemas": { "Person": { "subject": {}, "properties": {} } } }
			JSON,
			'/schemas/Person/subject'
		);
	}

	public function testRejectsAWrongFormatVersion(): void {
		$this->assertInvalidAt(
			<<<JSON
			{ "version": 2, "schemas": { "Person": { "subject": { "class": "edm:X" }, "properties": {} } }, "prefixes": { "edm": "http://europeana.eu/edm/" } }
			JSON,
			'/version'
		);
	}

	public function testRejectsAPredicateWhoseCurieDoesNotResolve(): void {
		// The "crm" prefix is not declared, so this predicate cannot be expanded and is rejected rather
		// than silently reinterpreted.
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "edm": "http://www.europeana.eu/schemas/edm/" },
				"schemas": {
					"Artwork": {
						"subject": { "class": "edm:ProvidedCHO" },
						"properties": {
							"Creator": { "predicate": "crm:P14_carried_out_by" }
						}
					}
				}
			}
			JSON,
			'/schemas/Artwork/properties/Creator/predicate'
		);
	}

	public function testRejectsASubjectClassWhoseCurieDoesNotResolve(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "edm": "http://www.europeana.eu/schemas/edm/" },
				"schemas": {
					"Artwork": {
						"subject": { "class": "crm:E22_Human-Made_Object" },
						"properties": {}
					}
				}
			}
			JSON,
			'/schemas/Artwork/subject/class'
		);
	}

	/**
	 * The #1029 lesson at the Mapping boundary: a predicate crafted to break out of its IRI must be
	 * rejected at save time, so no stored Mapping can forge triples in the projected document.
	 */
	public function testRejectsAPredicateThatWouldBreakOutOfItsIri(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "dc": "http://purl.org/dc/elements/1.1/" },
				"schemas": {
					"Person": {
						"subject": { "class": "http://example.org/CHO" },
						"properties": {
							"Name": { "predicate": "dc:title> <http://evil.example/s> <http://evil/p> <http://evil/o" }
						}
					}
				}
			}
			JSON,
			'/schemas/Person/properties/Name/predicate'
		);
	}

	public function testRejectsAPrefixNamespaceThatWouldBreakOutOfThePrefixTable(): void {
		// An unsafe namespace reaches the serializer's @prefix table even if no term uses it.
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "evil": "http://evil.example/\\"> .# " },
				"schemas": {
					"Person": {
						"subject": { "class": "http://example.org/CHO" },
						"properties": {}
					}
				}
			}
			JSON,
			'/prefixes/evil'
		);
	}

	/**
	 * @dataProvider validLanguageTagProvider
	 */
	public function testAcceptsABcp47LanguageTag( string $lang ): void {
		$this->assertValid( $this->mappingWithLang( $lang ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function validLanguageTagProvider(): array {
		return [
			'primary subtag only' => [ 'en' ],
			'region subtag' => [ 'en-US' ],
			'lowercase region' => [ 'pt-BR' ],
		];
	}

	/**
	 * A language tag outside the BCP-47 shape is rejected at save time, so it can never reach the
	 * serializer and smuggle a datatype or a `"` into the `"lexical"@tag` literal.
	 *
	 * @dataProvider invalidLanguageTagProvider
	 */
	public function testRejectsANonBcp47LanguageTag( string $lang ): void {
		$this->assertInvalidAt( $this->mappingWithLang( $lang ), '/schemas/Person/properties/Name/lang' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidLanguageTagProvider(): array {
		return [
			'underscore separator' => [ 'en_US' ],
			'trailing space' => [ 'en ' ],
			'empty trailing subtag' => [ 'en-' ],
			'datatype injection' => [ 'en"^^xsd:evil' ],
		];
	}

	private function mappingWithLang( string $lang ): string {
		$encodedLang = json_encode( $lang );

		return <<<JSON
			{
				"version": 1,
				"prefixes": { "dc": "http://purl.org/dc/elements/1.1/" },
				"schemas": {
					"Person": {
						"subject": { "class": "http://example.org/CHO" },
						"properties": {
							"Name": { "predicate": "dc:title", "lang": {$encodedLang} }
						}
					}
				}
			}
			JSON;
	}

	public function testRejectsAPropertyThatSetsBothLanguageAndDatatype(): void {
		// An RDF literal cannot carry both a language tag and a datatype.
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "dc": "http://purl.org/dc/elements/1.1/", "xsd": "http://www.w3.org/2001/XMLSchema#" },
				"schemas": {
					"Person": {
						"subject": { "class": "http://example.org/CHO" },
						"properties": {
							"Name": { "predicate": "dc:title", "lang": "en", "datatype": "xsd:string" }
						}
					}
				}
			}
			JSON,
			'/schemas/Person/properties/Name'
		);
	}

	private function validMapping(): string {
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
							"Website": { "predicate": "edm:isShownAt" },
							"Author": { "predicate": "dc:creator" }
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

	/**
	 * @dataProvider demoMappingProvider
	 */
	public function testDemoDataMappingIsValid( string $json ): void {
		$this->assertValid( $json );
	}

	public function demoMappingProvider(): iterable {
		$dir = new \DirectoryIterator( __DIR__ . '/../../../../DemoData/Mapping' );

		foreach ( $dir as $fileinfo ) {
			if ( !$fileinfo->isDot() && $fileinfo->getExtension() === 'json' ) {
				yield $fileinfo->getFilename() => [ TestData::getFileContents( 'Mapping/' . $fileinfo->getFilename() ) ];
			}
		}
	}

}
