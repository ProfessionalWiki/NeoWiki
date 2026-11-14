<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\ReleaseContent;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Mapping\LinkDirection;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeScope;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SubjectMapping;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPersistenceDeserializer
 */
class ReleasedMappingsTest extends TestCase {

	use ReadsReleaseContent;

	/**
	 * @dataProvider mappingProvider
	 */
	public function testReadsEverySchemaEntry( string $release, string $mappingName ): void {
		$this->assertSame(
			array_keys( self::decodedReleaseContent( $release, 'Mapping', $mappingName )['schemas'] ),
			array_keys( $this->readMapping( $release, $mappingName )->schemas )
		);
	}

	public static function mappingProvider(): iterable {
		return self::releaseContentPages( 'Mapping' );
	}

	public function testReadsPrefixes(): void {
		$this->assertSame(
			[ 'crm' => 'http://www.cidoc-crm.org/cidoc-crm/' ],
			$this->readMapping( '0.1.0', 'CIDOC-CRM' )->prefixes
		);
	}

	public function testReadsTheClassAndLabelPredicateOfSubjects(): void {
		$this->assertEquals(
			new SubjectMapping( class: 'edm:Agent', labelPredicate: 'foaf:name' ),
			$this->readMapping( '0.1.0', 'EDM' )->schemas['Person']->subject
		);
	}

	public function testReadsNodes(): void {
		$this->assertEquals(
			[
				'birth' => new NodeMapping( class: 'crm:E67_Birth', linkPredicate: 'crm:P98i_was_born' ),
				'birthTimespan' => new NodeMapping(
					class: 'crm:E52_Time-Span',
					linkPredicate: 'crm:P4_has_time-span',
					parent: 'birth'
				),
				'appellation' => new NodeMapping(
					class: 'crm:E41_Appellation',
					linkPredicate: 'crm:P1_is_identified_by',
					scope: NodeScope::Value
				),
			],
			$this->readMapping( '0.1.0', 'CIDOC-CRM' )->schemas['Person']->nodes
		);
	}

	public function testReadsTheLinkDirectionOfNodes(): void {
		$this->assertEquals(
			[
				'aggregation' => new NodeMapping(
					class: 'ore:Aggregation',
					linkPredicate: 'edm:aggregatedCHO',
					linkDirection: LinkDirection::FromNode
				),
			],
			$this->readMapping( '0.1.0', 'EDM' )->schemas['Artwork']->nodes
		);
	}

	public function testReadsPropertiesAttachedToNodes(): void {
		$this->assertEquals(
			new PropertyMapping( predicate: 'crm:P82_at_some_time_within', node: 'birthTimespan' ),
			$this->readMapping( '0.1.0', 'CIDOC-CRM' )->schemas['Person']->properties->get( 'Birth date' )
		);
	}

	public function testReadsTheLanguageAndDatatypeOfProperties(): void {
		$mapping = $this->readMapping( '0.1.0', 'EDM' );

		$this->assertEquals(
			new PropertyMapping( predicate: 'skos:note', language: 'en' ),
			$mapping->schemas['Person']->properties->get( 'Description' )
		);
		$this->assertEquals(
			new PropertyMapping( predicate: 'rdaGr2:dateOfBirth', datatype: 'xsd:gYear' ),
			$mapping->schemas['Artist']->properties->get( 'Born' )
		);
	}

	public function testReadsContributions(): void {
		$birth = $this->readMapping( '0.1.0', 'EDM' )->schemas['Birth'];

		$this->assertNull( $birth->subject );
		$this->assertEquals(
			[
				'Brought into life' => new PropertyMappings( [
					'Date' => new PropertyMapping( predicate: 'rdaGr2:dateOfBirth' ),
					'Took place at' => new PropertyMapping( predicate: 'rdaGr2:placeOfBirth' ),
				] ),
			],
			$birth->contributions
		);
	}

	private function readMapping( string $release, string $mappingName ): Mapping {
		return NeoWikiExtension::getInstance()->getMappingPersistenceDeserializer()->deserialize(
			new MappingName( $mappingName ),
			self::releaseContent( $release, 'Mapping', $mappingName )
		);
	}

}
