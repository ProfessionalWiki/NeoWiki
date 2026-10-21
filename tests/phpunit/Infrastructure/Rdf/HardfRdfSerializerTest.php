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
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;
use Psr\Log\LogLevel;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfStreamWriter
 */
class HardfRdfSerializerTest extends TestCase {

	private const string EXAMPLE_NAMESPACE = 'https://example.org/';
	private const string FORGING_IRI = "rdfs:x;rdfs:label'forged'";

	private RdfNamespaces $ns;
	private LegacyLoggerSpy $logger;

	protected function setUp(): void {
		$this->ns = new RdfNamespaces( 'https://wiki.example' );
		$this->logger = new LegacyLoggerSpy();
	}

	private function serializer(): HardfRdfSerializer {
		return new HardfRdfSerializer( $this->ns->prefixMap(), $this->logger );
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

	public function testTurtleWritesEachResourceAsOneBlockWhenItsTriplesArriveApart(): void {
		$output = $this->exampleSerializer()->serialize( $this->workTriplesSplitByAProductionTriple(), RdfFormat::Turtle );

		$this->assertSameDocument(
			$this->documentWithExamplePrefix(
				'ex:work ex:producedBy ex:production;',
				'    ex:keptAt ex:wallaceCollection.',
				'ex:production ex:carriedOutBy ex:hals.',
			),
			$output
		);
	}

	private function workTriplesSplitByAProductionTriple(): QuadList {
		return new QuadList(
			$this->exampleQuad( 'work', 'producedBy', 'production' ),
			$this->exampleQuad( 'production', 'carriedOutBy', 'hals' ),
			$this->exampleQuad( 'work', 'keptAt', 'wallaceCollection' ),
		);
	}

	private function exampleQuad( string $subject, string $predicate, string $object ): Quad {
		return $this->exampleQuadIn( 'page', $subject, $predicate, $object );
	}

	private function exampleQuadIn( string $graph, string $subject, string $predicate, string $object ): Quad {
		return new Quad( $this->example( $subject ), $this->example( $predicate ), $this->example( $object ), $this->example( $graph ) );
	}

	private function example( string $localName ): Iri {
		return new Iri( self::EXAMPLE_NAMESPACE . $localName );
	}

	private function exampleSerializer(): HardfRdfSerializer {
		return new HardfRdfSerializer( [ 'ex' => self::EXAMPLE_NAMESPACE ], $this->logger );
	}

	private function documentWithExamplePrefix( string ...$lines ): string {
		return '@prefix ex: <' . self::EXAMPLE_NAMESPACE . ">.\n\n" . implode( "\n", $lines ) . "\n";
	}

	private function assertSameDocument( string $expected, string $actual ): void {
		$this->assertSame( $this->withoutLayoutWhitespace( $expected ), $this->withoutLayoutWhitespace( $actual ) );
	}

	private function withoutLayoutWhitespace( string $document ): string {
		return trim( preg_replace( '/\s*\n\s*/', "\n", $document ) );
	}

	public function testTurtleWritesEachResourcesTypesFirstInItsOwnBlock(): void {
		// hals's type arrives before work's, and work's second type after one of its other triples.
		$quads = new QuadList(
			$this->exampleQuad( 'work', 'madeBy', 'hals' ),
			$this->exampleTypeQuad( 'hals', 'Person' ),
			$this->exampleTypeQuad( 'work', 'Painting' ),
			$this->exampleQuad( 'work', 'paintedIn', 'haarlem' ),
			$this->exampleTypeQuad( 'work', 'Artwork' ),
			$this->exampleQuad( 'hals', 'bornIn', 'antwerp' ),
			$this->exampleQuad( 'hals', 'diedIn', 'haarlem' ),
		);

		$output = $this->exampleSerializer()->serialize( $quads, RdfFormat::Turtle );

		$this->assertSameDocument(
			$this->documentWithExamplePrefix(
				'ex:work a ex:Painting, ex:Artwork;',
				'    ex:madeBy ex:hals;',
				'    ex:paintedIn ex:haarlem.',
				'ex:hals a ex:Person;',
				'    ex:bornIn ex:antwerp;',
				'    ex:diedIn ex:haarlem.',
			),
			$output
		);
	}

	private function exampleTypeQuad( string $subject, string $class ): Quad {
		return new Quad( $this->example( $subject ), $this->ns->rdfType(), $this->example( $class ), $this->example( 'page' ) );
	}

	public function testTriGWritesEachResourceAsOneBlockWhenItsTriplesArriveApart(): void {
		$output = $this->exampleSerializer()->serialize( $this->workTriplesSplitByAProductionTriple(), RdfFormat::TriG );

		$this->assertSameDocument(
			$this->documentWithExamplePrefix(
				'ex:page {',
				'ex:work ex:producedBy ex:production;',
				'    ex:keptAt ex:wallaceCollection.',
				'ex:production ex:carriedOutBy ex:hals',
				'}',
			),
			$output
		);
	}

	public function testStreamingWriterReturnsTheTriplesOfEachWriteRightAway(): void {
		$writer = $this->exampleSerializer()->newWriter( RdfFormat::TriG );

		$firstPage = $writer->write(
			new QuadList( $this->exampleQuadIn( 'cavalierPage', 'laughingCavalier', 'madeBy', 'hals' ) )
		);

		$this->assertStringContainsString( 'ex:laughingCavalier ex:madeBy ex:hals', $firstPage );
	}

	public function testStreamingWriterWritesEachQuadOnceAcrossWrites(): void {
		$writer = $this->exampleSerializer()->newWriter( RdfFormat::TriG );
		$cavalierPage = new QuadList( $this->exampleQuadIn( 'cavalierPage', 'laughingCavalier', 'madeBy', 'hals' ) );
		$windmillPage = new QuadList( $this->exampleQuadIn( 'windmillPage', 'windmill', 'madeBy', 'ruisdael' ) );

		$output = $writer->write( $cavalierPage ) . $writer->write( $windmillPage ) . $writer->finish();

		$this->assertCount( 2, ParsedRdf::canonicalQuads( $output ) );
	}

	/**
	 * @dataProvider misreadableIriProvider
	 */
	public function testIriNeverReadsAsAnotherTerm( string $iri ): void {
		$output = $this->serializer()->serialize(
			new QuadList( $this->websiteQuad( new Iri( $iri ) ) ),
			RdfFormat::Turtle
		);

		$this->assertStatesNothingBeyond(
			"<https://wiki.example/entity/s1demo8aaaaaab5> <https://wiki.example/prop/Website> <$iri> .",
			$output
		);
	}

	public static function misreadableIriProvider(): iterable {
		yield 'forging a triple through the rdfs prefix' => [ self::FORGING_IRI ];
		yield 'reading as another IRI through the rdfs prefix' => [ 'rdfs:label' ];
		yield 'resembling the rdfs namespace' => [ 'http://wwwxw3.org/2000/01/rdf-schema#true' ];
	}

	private function websiteQuad( Iri $website ): Quad {
		return new Quad(
			$this->ns->subject( new SubjectId( 's1demo8aaaaaab5' ) ),
			$this->ns->property( 'Website' ),
			$website,
			$this->ns->page( new PageId( 1 ) )
		);
	}

	private function assertStatesNothingBeyond( string $statedRdf, string $outputRdf ): void {
		$this->assertSame(
			[],
			array_values( array_diff( ParsedRdf::canonicalQuads( $outputRdf ), ParsedRdf::canonicalQuads( $statedRdf ) ) ),
			'The document holds a triple the serializer was never given.'
		);
	}

	/**
	 * @dataProvider quadWithTheForgingIriProvider
	 */
	public function testIriNeverReadsAsAnotherTermInAnyPosition( Quad $quad, string $statedTrig ): void {
		$output = $this->serializer()->serialize( new QuadList( $quad ), RdfFormat::TriG );

		$this->assertStatesNothingBeyond( $statedTrig, $output );
	}

	public static function quadWithTheForgingIriProvider(): iterable {
		$ns = new RdfNamespaces( 'https://wiki.example' );
		$forging = new Iri( self::FORGING_IRI );
		$subject = $ns->subject( new SubjectId( 's1demo8aaaaaab5' ) );
		$graph = $ns->page( new PageId( 1 ) );
		$label = new Literal( 'Berlin', $ns->xsd( 'string' ) );

		yield 'subject' => [
			new Quad( $forging, $ns->rdfsLabel(), $label, $graph ),
			"<https://wiki.example/page/1> { <rdfs:x;rdfs:label'forged'> <http://www.w3.org/2000/01/rdf-schema#label> \"Berlin\" }",
		];
		yield 'predicate' => [
			new Quad( $subject, $forging, $label, $graph ),
			"<https://wiki.example/page/1> { <https://wiki.example/entity/s1demo8aaaaaab5> <rdfs:x;rdfs:label'forged'> \"Berlin\" }",
		];
		yield 'graph' => [
			new Quad( $subject, $ns->rdfsLabel(), $label, $forging ),
			"<rdfs:x;rdfs:label'forged'> { <https://wiki.example/entity/s1demo8aaaaaab5> <http://www.w3.org/2000/01/rdf-schema#label> \"Berlin\" }",
		];
		yield 'datatype' => [
			new Quad( $subject, $ns->property( 'Age' ), new Literal( '42', $forging ), $graph ),
			"<https://wiki.example/page/1> { <https://wiki.example/entity/s1demo8aaaaaab5> <https://wiki.example/prop/Age> \"42\"^^<rdfs:x;rdfs:label'forged'> }",
		];
	}

	/**
	 * @dataProvider schemeLabelledPrefixProvider
	 * @param array<string, string> $prefixes
	 */
	public function testIrisOfASchemeUsedAsAPrefixLabelAreStillWritten( array $prefixes, string $website ): void {
		$quad = new Quad(
			new Iri( 'http://wiki.example/entity/s1demo8aaaaaab5' ),
			new Iri( 'http://wiki.example/prop/Website' ),
			new Iri( $website ),
			new Iri( 'http://wiki.example/page/1' )
		);

		$output = ( new HardfRdfSerializer( $prefixes, $this->logger ) )->serialize( new QuadList( $quad ), RdfFormat::Turtle );

		$this->assertSame(
			ParsedRdf::canonicalQuads(
				"<http://wiki.example/entity/s1demo8aaaaaab5> <http://wiki.example/prop/Website> <$website> ."
			),
			ParsedRdf::canonicalQuads( $output )
		);
	}

	public static function schemeLabelledPrefixProvider(): iterable {
		yield 'the scheme of its own namespace' => [ [ 'urn' => 'urn:example:ns/' ], 'urn:isbn:0451450523' ];
		yield 'a web scheme no namespace has' => [ [ 'https' => 'http://schema.org/' ], 'https://www.berlin.de/' ];
	}

	public function testIriThatWouldCloseItsAngleBracketsIsNotWritten(): void {
		$output = $this->serializer()->serialize(
			new QuadList( $this->websiteQuad( new Iri( 'https://evil.example/>,<https://evil.example/forged' ) ) ),
			RdfFormat::Turtle
		);

		$this->assertSame( [], ParsedRdf::canonicalQuads( $output ) );
	}

	public function testTripleWithAnIriThatCannotBeWrittenIsLeftOutWhileTheOthersStay(): void {
		$quads = new QuadList(
			$this->websiteQuad( new Iri( 'https://berlin.de/' ) ),
			$this->websiteQuad( new Iri( self::FORGING_IRI ) ),
			$this->websiteQuad( new Iri( 'https://visitberlin.de/' ) ),
		);

		$output = $this->serializer()->serialize( $quads, RdfFormat::Turtle );

		$this->assertSame(
			ParsedRdf::canonicalQuads( <<<TURTLE
				<https://wiki.example/entity/s1demo8aaaaaab5> <https://wiki.example/prop/Website> <https://berlin.de/>, <https://visitberlin.de/> .
				TURTLE ),
			ParsedRdf::canonicalQuads( $output )
		);
	}

	public function testLanguageTaggedLiteralKeepsItsTag(): void {
		$output = $this->serializer()->serialize(
			new QuadList( new Quad(
				$this->ns->subject( new SubjectId( 's1demo8aaaaaab5' ) ),
				$this->ns->property( 'Name' ),
				RdfLiteralFactory::languageTagged( 'Zinema', 'eu' ),
				$this->ns->page( new PageId( 1 ) )
			) ),
			RdfFormat::Turtle
		);

		$this->assertSame(
			ParsedRdf::canonicalQuads( '<https://wiki.example/entity/s1demo8aaaaaab5> <https://wiki.example/prop/Name> "Zinema"@eu .' ),
			ParsedRdf::canonicalQuads( $output )
		);
	}

	public function testLeavingATripleOutLogsAWarningNamingItsIri(): void {
		$this->serializer()->serialize( new QuadList( $this->websiteQuad( new Iri( self::FORGING_IRI ) ) ), RdfFormat::Turtle );

		$this->assertCount( 1, $this->logger->getLogCalls()->getMessages() );
		$this->assertSame( LogLevel::WARNING, $this->logger->getFirstLogCall()->getLevel() );
		$this->assertStringContainsString( self::FORGING_IRI, $this->logger->getLogCalls()->getMessages()[0] );
	}

}
