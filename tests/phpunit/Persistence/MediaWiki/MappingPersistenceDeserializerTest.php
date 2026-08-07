<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Mapping\LinkDirection;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeScope;
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

		$this->assertSame( 'edm:ProvidedCHO', $mapping->forSchema( new SchemaName( 'Person' ) )?->subject?->class );
		$this->assertSame( 'edm:Place', $mapping->forSchema( new SchemaName( 'City' ) )?->subject?->class );
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

		$this->assertSame( 'edm:Agent', $mapping->forSchema( new SchemaName( 'Person' ) )?->subject?->class );
		$this->assertSame( 'edm:Place', $mapping->forSchema( new SchemaName( 'City' ) )?->subject?->class );
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

		$this->assertSame( 'edm:Agent', $mapping->forSchema( new SchemaName( 'Person' ) )?->subject?->class );
		$this->assertSame( 'edm:Place', $mapping->forSchema( new SchemaName( 'City' ) )?->subject?->class );
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

	/**
	 * Only the one known format version is read: a page in any other reads as no Mapping at all, which
	 * degrades to an unknown projection rather than a half-understood one.
	 */
	public function testThrowsOnAnUnknownFormatVersion(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->deserialize( '{ "version": 2, "schemas": { "Person": { "subject": { "class": "edm:Agent" } } } }' );
	}

	// Structural tier: synthesized nodes and contributions.

	public function testDeserializesTheSubjectsExtraLabelPredicate(): void {
		$subject = $this->deserialize( $this->structuralJson() )->forSchema( new SchemaName( 'Person' ) )?->subject;

		$this->assertSame( 'crm:E21_Person', $subject?->class );
		$this->assertSame( 'foaf:name', $subject->labelPredicate );
	}

	public function testDeserializesASubjectScopedNode(): void {
		$birth = $this->personNodes()['birth'] ?? null;

		$this->assertSame( 'crm:E67_Birth', $birth?->class );
		$this->assertSame( 'crm:P98i_was_born', $birth->linkPredicate );
		$this->assertNull( $birth->parent );
		$this->assertSame( NodeScope::Subject, $birth->scope );
	}

	public function testDeserializesANestedNodesParent(): void {
		$this->assertSame( 'birth', $this->personNodes()['birthTimespan']?->parent );
	}

	public function testDeserializesAPerValueNodesScope(): void {
		$this->assertSame( NodeScope::Value, $this->personNodes()['appellation']?->scope );
	}

	public function testANodeWithoutALinkDirectionDefaultsToToNode(): void {
		$this->assertSame( LinkDirection::ToNode, $this->personNodes()['birth']?->linkDirection );
	}

	public function testDeserializesAnExplicitToNodeLinkDirection(): void {
		$this->assertSame( LinkDirection::ToNode, $this->personNodes()['birthTimespan']?->linkDirection );
	}

	public function testDeserializesAReversedNodesLinkDirection(): void {
		$this->assertSame( LinkDirection::FromNode, $this->personNodes()['death']?->linkDirection );
	}

	public function testSkipsANodeWithAnUnknownLinkDirection(): void {
		// Save validation rejects any other value, but an import can store one. Defaulting it would emit
		// the node's link triple the wrong way round, so the node goes instead — as it does when its terms
		// are missing.
		$nodes = $this->deserialize( <<<'JSON'
			{
				"version": 1,
				"prefixes": { "ore": "http://www.openarchives.org/ore/terms/" },
				"schemas": {
					"Artwork": {
						"subject": { "class": "http://www.europeana.eu/schemas/edm/ProvidedCHO" },
						"nodes": {
							"forwards": {
								"class": "ore:Aggregation",
								"linkPredicate": "http://www.europeana.eu/schemas/edm/aggregatedCHO"
							},
							"sideways": {
								"class": "ore:Aggregation",
								"linkPredicate": "http://www.europeana.eu/schemas/edm/aggregatedCHO",
								"linkDirection": "eitherWay"
							},
							"backwards": {
								"class": "ore:Aggregation",
								"linkPredicate": "http://www.europeana.eu/schemas/edm/aggregatedCHO",
								"linkDirection": "fromNode"
							}
						}
					}
				}
			}
			JSON )->forSchema( new SchemaName( 'Artwork' ) )?->nodes ?? [];

		$this->assertSame( [ 'forwards', 'backwards' ], array_keys( $nodes ) );
	}

	public function testSkipsANodeWithoutTheTermsItNeeds(): void {
		// Shapes save validation rejects: a node with no class, and one with no link predicate. Neither
		// can be placed in the graph, so both are dropped while their valid sibling survives.
		$nodes = $this->deserialize( <<<'JSON'
			{
				"version": 1,
				"prefixes": { "crm": "http://www.cidoc-crm.org/cidoc-crm/" },
				"schemas": {
					"Person": {
						"subject": { "class": "crm:E21_Person" },
						"nodes": {
							"classless": { "linkPredicate": "crm:P98i_was_born" },
							"birth": { "class": "crm:E67_Birth", "linkPredicate": "crm:P98i_was_born" },
							"unlinked": { "class": "crm:E52_Time-Span" }
						}
					}
				}
			}
			JSON )->forSchema( new SchemaName( 'Person' ) )?->nodes ?? [];

		$this->assertSame( [ 'birth' ], array_keys( $nodes ) );
	}

	/**
	 * @return array<string, NodeMapping>
	 */
	private function personNodes(): array {
		return $this->deserialize( $this->structuralJson() )->forSchema( new SchemaName( 'Person' ) )?->nodes ?? [];
	}

	public function testDeserializesThePropertysNodeAttachment(): void {
		$properties = $this->deserialize( $this->structuralJson() )->forSchema( new SchemaName( 'Person' ) )?->properties;

		$this->assertSame( 'birthTimespan', $properties?->get( 'Birth date' )?->node );
		$this->assertNull( $properties->get( 'Description' )?->node );
	}

	public function testDeserializesAContributionKeyedByItsRelation(): void {
		$contributions = $this->deserialize( $this->structuralJson() )->forSchema( new SchemaName( 'Birth' ) )?->contributions;

		$this->assertSame( [ 'Brought into life' ], array_keys( $contributions ?? [] ) );
		$this->assertSame(
			'rdaGr2:dateOfBirth',
			$contributions['Brought into life']->get( 'Date' )?->predicate
		);
	}

	public function testSkipsAContributionWithNoUsableProperties(): void {
		// Save validation requires at least one property; an import can store an entry whose only
		// property lacks a predicate, which would contribute nothing.
		$birth = $this->deserialize( <<<'JSON'
			{
				"version": 1,
				"schemas": {
					"Birth": {
						"contributions": {
							"Brought into life": { "Date": { "lang": "en" } }
						}
					}
				}
			}
			JSON )->forSchema( new SchemaName( 'Birth' ) );

		$this->assertNull( $birth, 'An entry left with neither a subject nor a contribution is skipped whole.' );
	}

	public function testDeserializesAPropertyWhoseNameIsAllDigits(): void {
		// A year column is a realistic property name, and json_decode makes it an int array key.
		$properties = $this->deserialize( <<<'JSON'
			{
				"version": 1,
				"schemas": {
					"Person": {
						"subject": { "class": "http://example.org/Person" },
						"properties": { "2020": { "predicate": "http://example.org/note" } }
					}
				}
			}
			JSON )->forSchema( new SchemaName( 'Person' ) )?->properties;

		$this->assertSame( 'http://example.org/note', $properties?->get( '2020' )?->predicate );
	}

	public function testAContributesOnlyEntryHasNoSubject(): void {
		$this->assertNull( $this->deserialize( $this->structuralJson() )->forSchema( new SchemaName( 'Birth' ) )?->subject );
	}

	public function testSkipsAnEntryThatNeitherProjectsNorContributes(): void {
		// A shape only an import can store, since save validation rejects it.
		$mapping = $this->deserialize( <<<JSON
			{
				"version": 1,
				"schemas": {
					"Person": { "subject": { "class": "http://example.org/Person" } },
					"Empty": {}
				}
			}
			JSON );

		$this->assertNotNull( $mapping->forSchema( new SchemaName( 'Person' ) ) );
		$this->assertNull( $mapping->forSchema( new SchemaName( 'Empty' ) ) );
	}

	private function structuralJson(): string {
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
							},
							"death": {
								"class": "crm:E69_Death",
								"linkPredicate": "crm:P100_was_death_of",
								"linkDirection": "fromNode"
							}
						},
						"properties": {
							"Description": { "predicate": "crm:P3_has_note" },
							"Birth date": { "predicate": "crm:P82_at_some_time_within", "node": "birthTimespan" },
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
