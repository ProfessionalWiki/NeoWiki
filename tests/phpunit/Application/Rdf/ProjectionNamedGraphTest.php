<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SchemaMapping;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * Regression test for #1053 (discussion #996): named-graph IRIs are qualified by projection. The same
 * page projected natively and through an ontology Mapping must land in DIFFERENT named graphs, so two
 * projections can share one triple store without the per-page replace sync of one wiping the other's
 * triples. The page RESOURCE IRI that appears inside the triples is unaffected — only the graph moves.
 *
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector
 * @covers \ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector
 * @covers \ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces
 */
class ProjectionNamedGraphTest extends TestCase {

	private const string PERSON_ID = 's1janeaaaaaaaa2';

	private RdfNamespaces $ns;

	protected function setUp(): void {
		$this->ns = new RdfNamespaces( 'https://wiki.example' );
	}

	public function testNativeAndOntologyProjectionsOfTheSamePageUseDifferentNamedGraphs(): void {
		$page = $this->page();

		$nativeGraph = $this->soleGraph( $this->nativeProjector()->projectPage( $page ) );
		$ontologyGraph = $this->soleGraph( $this->edmProjector()->projectPage( $page ) );

		$this->assertSame( 'https://wiki.example/graph/native/page/42', $nativeGraph );
		$this->assertSame( 'https://wiki.example/graph/edm/page/42', $ontologyGraph );
		$this->assertNotSame(
			$nativeGraph,
			$ontologyGraph,
			'A native and an ontology projection of the same page must not collide in one store.'
		);
	}

	public function testThePageResourceIriStaysUnqualifiedWhileItsGraphMoves(): void {
		$quads = $this->nativeProjector()->projectPage( $this->page() )->asArray();

		$pageTypeTriples = array_values( array_filter(
			$quads,
			fn ( Quad $quad ): bool => $quad->subject->value === 'https://wiki.example/page/42'
				&& $quad->predicate->equals( $this->ns->rdfType() )
		) );

		$this->assertCount(
			1,
			$pageTypeTriples,
			'The page resource IRI still appears in the triples as https://wiki.example/page/42.'
		);
		$this->assertSame(
			'https://wiki.example/graph/native/page/42',
			$pageTypeTriples[0]->graph->value,
			'while that triple now lives in the projection-qualified named graph.'
		);
	}

	private function nativeProjector(): RdfPageProjector {
		return new RdfPageProjector(
			RdfValueMapperRegistry::withCoreMappers(),
			$this->ns,
			new InMemorySchemaLookup(
				TestSchema::build( name: 'Person', properties: new PropertyDefinitions( [] ) )
			),
			new LegacyLoggerSpy(),
		);
	}

	private function edmProjector(): OntologyMappingProjector {
		return new OntologyMappingProjector(
			$this->personToEdmMapping(),
			$this->ns,
			RdfValueMapperRegistry::withCoreMappers(),
			new LegacyLoggerSpy(),
		);
	}

	private function personToEdmMapping(): Mapping {
		return new Mapping(
			name: new MappingName( 'edm' ),
			prefixes: [ 'edm' => 'http://www.europeana.eu/schemas/edm/' ],
			schemas: [
				'Person' => new SchemaMapping(
					schema: new SchemaName( 'Person' ),
					subjectClass: 'edm:Agent',
					properties: new PropertyMappings( [] )
				),
			],
		);
	}

	private function page(): Page {
		return TestPage::build(
			id: 42,
			mainSubject: TestSubject::build(
				id: self::PERSON_ID,
				label: 'Jane',
				schemaName: new SchemaName( 'Person' ),
				statements: new StatementList( [
					TestStatement::build( 'Name', new StringValue( 'Jane' ), 'text' ),
				] )
			),
		);
	}

	private function soleGraph( QuadList $quads ): string {
		$graphs = array_values( array_unique( array_map(
			static fn ( Quad $quad ): string => $quad->graph->value,
			$quads->asArray()
		) ) );

		$this->assertCount( 1, $graphs, 'A projection places every quad in exactly one named graph.' );

		return $graphs[0];
	}

}
