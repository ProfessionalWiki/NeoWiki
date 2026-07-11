<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces
 */
class RdfNamespacesTest extends TestCase {

	private function namespaces(): RdfNamespaces {
		return new RdfNamespaces( 'https://wiki.example' );
	}

	public function testSubjectIriUsesEntityPath(): void {
		$this->assertSame(
			'https://wiki.example/entity/s1demo8aaaaaab5',
			$this->namespaces()->subject( new SubjectId( 's1demo8aaaaaab5' ) )->value
		);
	}

	public function testPropertyIriSubstitutesSpacesWithUnderscores(): void {
		$this->assertSame(
			'https://wiki.example/prop/Has_author',
			$this->namespaces()->property( 'Has author' )->value
		);
	}

	public function testSchemaClassIriUsesSchemaPath(): void {
		$this->assertSame(
			'https://wiki.example/schema/Person',
			$this->namespaces()->schemaClass( new SchemaName( 'Person' ) )->value
		);
	}

	public function testRelationNodeIriUsesRelationPath(): void {
		$this->assertSame(
			'https://wiki.example/relation/r1demo8aaaaaaD6',
			$this->namespaces()->relationNode( new RelationId( 'r1demo8aaaaaaD6' ) )->value
		);
	}

	public function testPageIriUsesPagePath(): void {
		$this->assertSame(
			'https://wiki.example/page/42',
			$this->namespaces()->page( new PageId( 42 ) )->value
		);
	}

	public function testVocabularyTermUsesOntologyPath(): void {
		$this->assertSame(
			'https://wiki.example/ontology/hasSubject',
			$this->namespaces()->term( RdfNamespaces::TERM_HAS_SUBJECT )->value
		);
	}

	public function testTrailingSlashInBaseUriIsNormalised(): void {
		$namespaces = new RdfNamespaces( 'https://wiki.example/' );

		$this->assertSame(
			'https://wiki.example/page/7',
			$namespaces->page( new PageId( 7 ) )->value
		);
	}

	public function testStandardVocabularyIsIndependentOfBase(): void {
		$namespaces = $this->namespaces();

		$this->assertSame( 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $namespaces->rdfType()->value );
		$this->assertSame( 'http://www.w3.org/2000/01/rdf-schema#label', $namespaces->rdfsLabel()->value );
		$this->assertSame( 'http://purl.org/dc/terms/created', $namespaces->dctermsCreated()->value );
		$this->assertSame( 'http://www.w3.org/2001/XMLSchema#dateTime', $namespaces->xsd( 'dateTime' )->value );
	}

	public function testPrefixMapCoversEveryNeoAndStandardNamespace(): void {
		$this->assertSame(
			[
				'neo' => 'https://wiki.example/ontology/',
				'neo-subj' => 'https://wiki.example/entity/',
				'neo-prop' => 'https://wiki.example/prop/',
				'neo-schema' => 'https://wiki.example/schema/',
				'neo-rel' => 'https://wiki.example/relation/',
				'neo-page' => 'https://wiki.example/page/',
				'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
				'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
				'xsd' => 'http://www.w3.org/2001/XMLSchema#',
				'dcterms' => 'http://purl.org/dc/terms/',
			],
			$this->namespaces()->prefixMap()
		);
	}

}
