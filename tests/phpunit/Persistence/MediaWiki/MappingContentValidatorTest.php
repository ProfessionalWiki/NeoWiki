<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingContentValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingContentValidator
 */
class MappingContentValidatorTest extends MediaWikiIntegrationTestCase {

	private function newValidator(): MappingContentValidator {
		return MappingContentValidator::newInstance( $this->getServiceContainer()->getTitleParser() );
	}

	private function assertValid( string $json ): void {
		$validator = $this->newValidator();
		$this->assertTrue( $validator->validate( $json ), 'Expected valid, got: ' . implode( '; ', $validator->getErrors() ) );
		$this->assertSame( [], $validator->getErrors() );
	}

	private function assertInvalidAt( string $json, string $expectedErrorPointer ): void {
		$validator = $this->newValidator();
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

	public function testRejectsAnUnknownFormatVersion(): void {
		$this->assertInvalidAt(
			<<<JSON
			{ "version": 2, "schemas": { "Person": { "subject": { "class": "edm:X" }, "properties": {} } }, "prefixes": { "edm": "http://europeana.eu/edm/" } }
			JSON,
			'/version'
		);
	}

	/**
	 * The projector matches a Schema name byte for byte against the name a Subject carries, while a page
	 * lookup normalizes underscores and the leading capital. A key that only resolves after normalization
	 * would link to its Schema page yet never project, so it is rejected with the form to use.
	 */
	public function testRejectsASchemaNameThatIsNotTheCanonicalPageTitle(): void {
		foreach ( [ 'Birth_place', 'birth place', ' Person' ] as $schemaName ) {
			$this->assertInvalidAt(
				(string)json_encode( [
					'version' => 1,
					'schemas' => [
						$schemaName => [
							'subject' => [ 'class' => 'http://example.org/ns/Person' ],
							'properties' => (object)[],
						],
					],
				] ),
				'/schemas/' . $schemaName
			);
		}
	}

	public function testSaysWhichFormANonCanonicalSchemaNameShouldTake(): void {
		$validator = $this->newValidator();
		$validator->validate( (string)json_encode( [
			'version' => 1,
			'schemas' => [
				'Birth_place' => [
					'subject' => [ 'class' => 'http://example.org/ns/Place' ],
					'properties' => (object)[],
				],
			],
		] ) );

		$this->assertStringContainsString(
			'must be written as "Birth place"',
			$validator->getErrors()['/schemas/Birth_place'] ?? ''
		);
	}

	public function testRejectsASchemaNameThatIsNotAValidPageTitle(): void {
		$this->assertInvalidAt(
			(string)json_encode( [
				'version' => 1,
				'schemas' => [
					'Bad|Name' => [
						'subject' => [ 'class' => 'http://example.org/ns/Person' ],
						'properties' => (object)[],
					],
				],
			] ),
			'/schemas/Bad|Name'
		);
	}

	/**
	 * The deserializer drops a reserved name, so accepting one here would store an entry that renders as
	 * a mapped Schema but projects nothing.
	 */
	public function testRejectsAReservedSchemaName(): void {
		$this->assertInvalidAt(
			(string)json_encode( [
				'version' => 1,
				'schemas' => [
					'Page' => [
						'subject' => [ 'class' => 'http://example.org/ns/Person' ],
						'properties' => (object)[],
					],
				],
			] ),
			'/schemas/Page'
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

	// Structural tier: synthesized nodes and contributions.

	public function testAcceptsAMappingWithANestedNodeChainAndAContribution(): void {
		$this->assertValid( $this->structuralMapping() );
	}

	/**
	 * A Schema entry may project its Subject, contribute to others, or both — but an empty entry says
	 * nothing and is a mistake worth catching.
	 */
	public function testRejectsAnEntryThatNeitherProjectsNorContributes(): void {
		$this->assertInvalidAt( '{ "version": 1, "schemas": { "Person": {} } }', '/schemas/Person' );
	}

	public function testRejectsPropertiesWithoutASubjectToHangThemOn(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "dc": "http://purl.org/dc/elements/1.1/" },
				"schemas": {
					"Person": { "properties": { "Name": { "predicate": "dc:title" } } }
				}
			}
			JSON,
			'/schemas/Person'
		);
	}

	public function testRejectsALabelPredicateThatWouldBreakOutOfItsIri(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "foaf": "http://xmlns.com/foaf/0.1/" },
				"schemas": {
					"Person": {
						"subject": {
							"class": "http://example.org/Person",
							"labelPredicate": "foaf:name> <http://evil.example/s> <http://evil/p> <http://evil/o"
						}
					}
				}
			}
			JSON,
			'/schemas/Person/subject/labelPredicate'
		);
	}

	public function testRejectsANodeClassWhoseCurieDoesNotResolve(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"nodes": { "birth": { "class": "nosuch:E67_Birth", "linkPredicate": "crm:P98i_was_born" } }
					}
				}
			}
			JSON,
			'/schemas/Person/nodes/birth/class'
		);
	}

	public function testRejectsANodeLinkPredicateThatWouldBreakOutOfItsIri(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"nodes": {
							"birth": {
								"class": "crm:E67_Birth",
								"linkPredicate": "crm:P98i> <http://evil.example/s> <http://evil/p> <http://evil/o"
							}
						}
					}
				}
			}
			JSON,
			'/schemas/Person/nodes/birth/linkPredicate'
		);
	}

	public function testRejectsANodeKeyThatIsNotIdentifierShaped(): void {
		foreach ( [ '-birth', 'birth place', '' ] as $nodeKey ) {
			$this->assertInvalidAt( $this->mappingWithNodeKey( $nodeKey ), '/schemas/Person/nodes' );
		}
	}

	/**
	 * An all-digit node key survives JSON parsing as an integer key, which every consumer of the key
	 * would then have to handle — and nothing can reference it, since a `node` or `parent` naming it is
	 * pattern-checked as a string. Rejecting it removes the class of bug.
	 */
	public function testRejectsAnAllDigitNodeKey(): void {
		$this->assertInvalidAt( $this->mappingWithNodeKey( '2024' ), '/schemas/Person/nodes/2024' );
	}

	private function mappingWithNodeKey( string $nodeKey ): string {
		return (string)json_encode( [
			'version' => 1,
			'schemas' => [
				'Person' => [
					'subject' => [ 'class' => 'http://example.org/Person' ],
					'nodes' => [
						$nodeKey => [
							'class' => 'http://example.org/Birth',
							'linkPredicate' => 'http://example.org/wasBorn',
						],
					],
				],
			],
		] );
	}

	public function testRejectsANodeScopeThatIsNeitherSubjectNorValue(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"schemas": {
					"Person": {
						"subject": { "class": "http://example.org/Person" },
						"nodes": {
							"birth": {
								"class": "http://example.org/Birth",
								"linkPredicate": "http://example.org/wasBorn",
								"per": "page"
							}
						}
					}
				}
			}
			JSON,
			'/schemas/Person/nodes/birth/per'
		);
	}

	public function testRejectsALinkDirectionThatIsNeitherToNodeNorFromNode(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"schemas": {
					"Person": {
						"subject": { "class": "http://example.org/Person" },
						"nodes": {
							"birth": {
								"class": "http://example.org/Birth",
								"linkPredicate": "http://example.org/wasBorn",
								"linkDirection": "eitherWay"
							}
						}
					}
				}
			}
			JSON,
			'/schemas/Person/nodes/birth/linkDirection'
		);
	}

	public function testRejectsAPropertyAttachedToAnUndeclaredNode(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"nodes": { "birth": { "class": "crm:E67_Birth", "linkPredicate": "crm:P98i_was_born" } },
						"properties": { "Birth date": { "predicate": "crm:P82_at_some_time_within", "node": "death" } }
					}
				}
			}
			JSON,
			'/schemas/Person/properties/Birth date/node'
		);
	}

	public function testRejectsANodeWhoseParentIsNotDeclared(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"nodes": {
							"timespan": {
								"class": "crm:E52_Time-Span",
								"linkPredicate": "crm:P4_has_time-span",
								"parent": "birth"
							}
						}
					}
				}
			}
			JSON,
			'/schemas/Person/nodes/timespan/parent'
		);
	}

	public function testRejectsAParentCycle(): void {
		// A cycle never reaches the Subject, so nothing the nodes carry could be anchored.
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"nodes": {
							"birth": { "class": "crm:E67_Birth", "linkPredicate": "crm:P98i_was_born", "parent": "timespan" },
							"timespan": {
								"class": "crm:E52_Time-Span",
								"linkPredicate": "crm:P4_has_time-span",
								"parent": "birth"
							}
						}
					}
				}
			}
			JSON,
			'/schemas/Person/nodes/birth/parent'
		);
	}

	public function testRejectsASecondPropertyAttachedToAPerValueNode(): void {
		// A per-value node has one instance per value of the property that attaches to it, so a second
		// property would have no single instance to attach to.
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Artwork": {
						"subject": { "class": "crm:E22_Human-Made_Object" },
						"nodes": {
							"production": {
								"class": "crm:E12_Production",
								"linkPredicate": "crm:P108i_was_produced_by",
								"per": "value"
							}
						},
						"properties": {
							"Creator": { "predicate": "crm:P14_carried_out_by", "node": "production" },
							"Technique": { "predicate": "crm:P32_used_general_technique", "node": "production" }
						}
					}
				}
			}
			JSON,
			'/schemas/Artwork/properties/Technique/node'
		);
	}

	public function testRejectsAPerValueNodeUsedAsAParent(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Artwork": {
						"subject": { "class": "crm:E22_Human-Made_Object" },
						"nodes": {
							"production": {
								"class": "crm:E12_Production",
								"linkPredicate": "crm:P108i_was_produced_by",
								"per": "value"
							},
							"timespan": {
								"class": "crm:E52_Time-Span",
								"linkPredicate": "crm:P4_has_time-span",
								"parent": "production"
							}
						}
					}
				}
			}
			JSON,
			'/schemas/Artwork/nodes/timespan/parent'
		);
	}

	public function testRejectsAContributionWithNoProperties(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"schemas": {
					"Birth": { "contributions": { "Brought into life": {} } }
				}
			}
			JSON,
			'/schemas/Birth/contributions/Brought%20into%20life'
		);
	}

	public function testRejectsAContributionThroughANamelessRelation(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"schemas": {
					"Birth": { "contributions": { "": { "Date": { "predicate": "http://example.org/born" } } } }
				}
			}
			JSON,
			'/schemas/Birth/contributions'
		);
	}

	public function testRejectsAContributionPredicateWhoseCurieDoesNotResolve(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"schemas": {
					"Birth": {
						"contributions": {
							"Brought into life": { "Date": { "predicate": "rdaGr2:dateOfBirth" } }
						}
					}
				}
			}
			JSON,
			'/schemas/Birth/contributions/Brought into life/Date/predicate'
		);
	}

	/**
	 * A contribution names properties of the contributing Schema, which attach to the target Subject, so
	 * there is no node of the contributing entry for them to hang off.
	 */
	public function testRejectsANodeReferenceInsideAContribution(): void {
		$this->assertInvalidAt(
			<<<JSON
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Birth": {
						"subject": { "class": "crm:E67_Birth" },
						"nodes": { "timespan": { "class": "crm:E52_Time-Span", "linkPredicate": "crm:P4_has_time-span" } },
						"contributions": {
							"Brought into life": {
								"Date": { "predicate": "crm:P82_at_some_time_within", "node": "timespan" }
							}
						}
					}
				}
			}
			JSON,
			'/schemas/Birth/contributions/Brought%20into%20life/Date'
		);
	}

	private function structuralMapping(): string {
		return <<<JSON
			{
				"version": 1,
				"prefixes": {
					"crm": "http://www.cidoc-crm.org/cidoc-crm/",
					"rdaGr2": "http://rdvocab.info/ElementsGr2/",
					"foaf": "http://xmlns.com/foaf/0.1/"
				},
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person", "labelPredicate": "foaf:name" },
						"nodes": {
							"birth": { "class": "crm:E67_Birth", "linkPredicate": "crm:P98i_was_born" },
							"birthTimespan": {
								"class": "crm:E52_Time-Span",
								"linkPredicate": "crm:P4_has_time-span",
								"parent": "birth",
								"linkDirection": "toNode"
							},
							"appellation": {
								"class": "crm:E41_Appellation",
								"linkPredicate": "crm:P1_is_identified_by",
								"per": "value"
							}
						},
						"properties": {
							"Birth date": { "predicate": "crm:P82_at_some_time_within", "node": "birthTimespan" },
							"Birth place": { "predicate": "crm:P7_took_place_at", "node": "birth" },
							"Also known as": { "predicate": "crm:P190_has_symbolic_content", "node": "appellation" }
						}
					},
					"Birth": {
						"contributions": {
							"Brought into life": {
								"Date": { "predicate": "rdaGr2:dateOfBirth" },
								"Took place at": { "predicate": "rdaGr2:placeOfBirth" }
							}
						}
					}
				}
			}
			JSON;
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
