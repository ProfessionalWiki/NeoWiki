<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfStreamWriter
 */
class HardfRdfSerializerTest extends TestCase {

	private RdfNamespaces $ns;

	protected function setUp(): void {
		$this->ns = new RdfNamespaces( 'https://wiki.example' );
	}

	private function serializer(): HardfRdfSerializer {
		return new HardfRdfSerializer( $this->ns->prefixMap() );
	}

	private function personQuads( int $pageId, string $subjectId ): QuadList {
		$graph = $this->ns->page( new PageId( $pageId ) );
		$subject = $this->ns->subject( new SubjectId( $subjectId ) );

		return QuadList::fromArray( [
			new Quad( $subject, $this->ns->rdfType(), $this->ns->schemaClass( new SchemaName( 'Person' ) ), $graph ),
			new Quad( $subject, $this->ns->rdfsLabel(), new Literal( 'John Doe', $this->ns->xsd( 'string' ) ), $graph ),
			new Quad( $subject, $this->ns->property( 'Age' ), new Literal( '42', $this->ns->xsd( 'integer' ) ), $graph ),
			new Quad( $subject, $this->ns->property( 'Active' ), new Literal( 'true', $this->ns->xsd( 'boolean' ) ), $graph ),
			new Quad( $subject, $this->ns->property( 'Website' ), new Literal( 'https://x', $this->ns->xsd( 'anyURI' ) ), $graph ),
		] );
	}

	public function testTriGSerializationRoundTripsToTheSameQuadSetInTheNamedGraph(): void {
		$output = $this->serializer()->serialize( $this->personQuads( 1, 's1demo8aaaaaab5' ), RdfFormat::TriG );

		$expected = <<<TRIG
			@prefix neo-subj: <https://wiki.example/entity/> .
			@prefix neo-prop: <https://wiki.example/prop/> .
			@prefix neo-schema: <https://wiki.example/schema/> .
			@prefix neo-page: <https://wiki.example/page/> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
			neo-page:1 {
				neo-subj:s1demo8aaaaaab5 a neo-schema:Person ;
					rdfs:label "John Doe" ;
					neo-prop:Age 42 ;
					neo-prop:Active true ;
					neo-prop:Website "https://x"^^xsd:anyURI .
			}
			TRIG;

		$this->assertSame( ParsedRdf::canonicalQuads( $expected ), ParsedRdf::canonicalQuads( $output ) );
	}

	public function testTurtleSerializationEmitsTheSameTriplesWithoutAGraph(): void {
		$output = $this->serializer()->serialize( $this->personQuads( 1, 's1demo8aaaaaab5' ), RdfFormat::Turtle );

		$expected = <<<TURTLE
			@prefix neo-subj: <https://wiki.example/entity/> .
			@prefix neo-prop: <https://wiki.example/prop/> .
			@prefix neo-schema: <https://wiki.example/schema/> .
			@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
			@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
			neo-subj:s1demo8aaaaaab5 a neo-schema:Person ;
				rdfs:label "John Doe" ;
				neo-prop:Age 42 ;
				neo-prop:Active true ;
				neo-prop:Website "https://x"^^xsd:anyURI .
			TURTLE;

		$this->assertSame( ParsedRdf::canonicalQuads( $expected ), ParsedRdf::canonicalQuads( $output ) );
	}

	public function testTurtleOutputContainsNoNamedGraph(): void {
		$output = $this->serializer()->serialize( $this->personQuads( 1, 's1demo8aaaaaab5' ), RdfFormat::Turtle );

		$this->assertStringNotContainsString( 'neo-page:1 {', $output );
		$this->assertStringNotContainsString( '/page/1', $output );
	}

	public function testStreamingWriterGroupsQuadsFromEachPageIntoItsOwnGraph(): void {
		$writer = $this->serializer()->newWriter( RdfFormat::TriG );

		$output = $writer->write( $this->personQuads( 1, 's1demo8aaaaaab5' ) );
		$output .= $writer->write( $this->personQuads( 2, 's1demo8aaaaaac6' ) );
		$output .= $writer->finish();

		$quads = ParsedRdf::canonicalQuads( $output );

		$this->assertContains(
			implode( "\t", [
				'https://wiki.example/entity/s1demo8aaaaaab5',
				'http://www.w3.org/2000/01/rdf-schema#label',
				'"John Doe"',
				'https://wiki.example/page/1',
			] ),
			$quads
		);
		$this->assertContains(
			implode( "\t", [
				'https://wiki.example/entity/s1demo8aaaaaac6',
				'http://www.w3.org/2000/01/rdf-schema#label',
				'"John Doe"',
				'https://wiki.example/page/2',
			] ),
			$quads
		);
	}

	public function testOutputUsesReadablePrefixedNames(): void {
		$output = $this->serializer()->serialize( $this->personQuads( 1, 's1demo8aaaaaab5' ), RdfFormat::TriG );

		$this->assertStringContainsString( '@prefix neo-prop:', $output );
		$this->assertStringContainsString( 'neo-schema:Person', $output );
	}

	public function testLiteralWithEmbeddedQuotesIsEscapedInOutputAndStaysOneQuad(): void {
		$graph = $this->ns->page( new PageId( 3 ) );
		$subject = $this->ns->subject( new SubjectId( 's1demo8aaaaaab5' ) );
		$quads = new QuadList(
			new Quad( $subject, $this->ns->rdfsLabel(), new Literal( 'They said "hi"', $this->ns->xsd( 'string' ) ), $graph )
		);

		$output = $this->serializer()->serialize( $quads, RdfFormat::TriG );

		// The adapter must let hardf escape the embedded quotes, producing valid Turtle that parses
		// back to exactly one quad rather than a truncated or malformed literal.
		$this->assertStringContainsString( '\\"hi\\"', $output );
		$this->assertCount( 1, ParsedRdf::canonicalQuads( $output ) );
	}

}
