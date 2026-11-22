<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfTerm;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\RdfXmlSerializer;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdfXml;
use Psr\Log\LogLevel;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;
use WMDE\PsrLogTestDoubles\LogCall;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\Rdf\RdfXmlSerializer
 */
class RdfXmlSerializerTest extends TestCase {

	private const string PROPERTY = 'https://wiki.example/prop/Person/Birth_date';

	private RdfNamespaces $ns;
	private LegacyLoggerSpy $logger;

	protected function setUp(): void {
		$this->ns = new RdfNamespaces( 'https://wiki.example' );
		$this->logger = new LegacyLoggerSpy();
	}

	public function testWritesEachTripleWithItsObject(): void {
		$output = $this->serialize(
			$this->propertyQuad( $this->ns->rdfType(), $this->ns->rdf( 'Property' ) ),
			$this->propertyQuad( $this->ns->rdfsLabel(), RdfLiteralFactory::typed( 'Birth date', 'string' ) ),
			$this->propertyQuad( $this->ns->rdfsLabel(), RdfLiteralFactory::languageTagged( 'Geburtsdatum', 'de' ) ),
			$this->propertyQuad( $this->ns->rdfs( 'comment' ), RdfLiteralFactory::typed( '42', 'integer' ) ),
			$this->propertyQuad( $this->ns->rdfs( 'domain' ), $this->ns->schemaClass( new SchemaName( 'Person' ) ) ),
		);

		$this->assertSame(
			ParsedRdf::canonicalQuads( <<<TURTLE
				@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
				@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
				@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

				<https://wiki.example/prop/Person/Birth_date> a rdf:Property ;
					rdfs:label "Birth date", "Geburtsdatum"@de ;
					rdfs:comment 42 ;
					rdfs:domain <https://wiki.example/schema/Person> .
				TURTLE ),
			ParsedRdfXml::canonicalTriples( $output )
		);
	}

	public function testWritesATypedResourceAsAnElementNamedAfterItsType(): void {
		$output = $this->serialize(
			$this->propertyQuad( $this->ns->rdfType(), $this->ns->rdf( 'Property' ) ),
			$this->propertyQuad( $this->ns->rdfsLabel(), RdfLiteralFactory::typed( 'Birth date', 'string' ) ),
		);

		$this->assertStringContainsString( '<rdf:Property rdf:about="' . self::PROPERTY . '">', $output );
	}

	public function testDeclaresTheNamespaceOfASchemasProperties(): void {
		$output = $this->serialize( $this->propertyQuad( $this->ns->rdfType(), $this->ns->rdf( 'Property' ) ) );

		$this->assertStringContainsString( 'xmlns:Person="https://wiki.example/prop/Person/"', $output );
	}

	public function testDeclaresTheNamespaceOfASchemaWhoseNameIsNoXmlNameUnderAnotherLabel(): void {
		$output = $this->serialize( $this->typeQuad( $this->ns->property( new SchemaName( '2024 Exhibitions' ), 'Venue' ), $this->ns->rdf( 'Property' ) ) );

		$this->assertMatchesRegularExpression(
			'~\sxmlns:[A-Za-z_][A-Za-z0-9_.-]*="https://wiki\.example/prop/2024_Exhibitions/"~',
			$output
		);
	}

	public function testWritesTheTriplesOfEveryResource(): void {
		$output = $this->serialize(
			$this->propertyQuad( $this->ns->rdfType(), $this->ns->rdf( 'Property' ) ),
			$this->typeQuad( $this->ns->schemaClass( new SchemaName( 'Person' ) ), $this->ns->rdfs( 'Class' ) ),
		);

		$this->assertCount( 2, ParsedRdfXml::canonicalTriples( $output ) );
	}

	public function testMarkupInLiteralsAndIrisStaysText(): void {
		$output = $this->serialize(
			new Quad( new Iri( 'https://wiki.example/a?b=1&c=2' ), $this->ns->rdfsLabel(), RdfLiteralFactory::typed( 'R&D <"lab">', 'string' ), $this->ns->schemaGraph() ),
		);

		$this->assertSame(
			[ "https://wiki.example/a?b=1&c=2\thttp://www.w3.org/2000/01/rdf-schema#label\t\"R&D <\"lab\">\"\t" ],
			ParsedRdfXml::canonicalTriples( $output )
		);
	}

	public function testKeepsLineBreaksAndTabsInALiteral(): void {
		$output = $this->serialize( $this->propertyQuad( $this->ns->rdfs( 'comment' ), RdfLiteralFactory::typed( "line one\r\nline two\tend", 'string' ) ) );

		$this->assertSame(
			[ self::PROPERTY . "\thttp://www.w3.org/2000/01/rdf-schema#comment\t\"line one\r\nline two\tend\"\t" ],
			ParsedRdfXml::canonicalTriples( $output )
		);
	}

	public function testWritesALiteralWithACharacterBeyondTheBasicMultilingualPlane(): void {
		$output = $this->serialize( $this->propertyQuad( $this->ns->rdfsLabel(), RdfLiteralFactory::typed( 'Museum 🏛', 'string' ) ) );

		$this->assertSame(
			[ self::PROPERTY . "\thttp://www.w3.org/2000/01/rdf-schema#label\t\"Museum 🏛\"\t" ],
			ParsedRdfXml::canonicalTriples( $output )
		);
	}

	public function testTripleWithARelativeIriIsLeftOut(): void {
		$output = $this->serialize(
			$this->typeQuad( new Iri( 'wiki.example/schema/Person' ), $this->ns->rdfs( 'Class' ) ),
			$this->propertyQuad( $this->ns->rdfType(), $this->ns->rdf( 'Property' ) ),
		);

		$this->assertCount( 1, ParsedRdfXml::canonicalTriples( $output ) );
	}

	public function testTripleWithACharacterXmlCannotHoldIsLeftOutWhileTheOthersStay(): void {
		$output = $this->serialize(
			$this->propertyQuad( $this->ns->rdfs( 'comment' ), RdfLiteralFactory::typed( "bell \x07", 'string' ) ),
			$this->propertyQuad( $this->ns->rdfType(), $this->ns->rdf( 'Property' ) ),
		);

		$this->assertSame(
			[ self::PROPERTY . "\thttp://www.w3.org/1999/02/22-rdf-syntax-ns#type\thttp://www.w3.org/1999/02/22-rdf-syntax-ns#Property\t" ],
			ParsedRdfXml::canonicalTriples( $output )
		);
	}

	public function testTripleWhosePredicateHasNoDeclaredPrefixIsLeftOut(): void {
		$output = $this->serialize(
			$this->propertyQuad( $this->ns->rdfType(), $this->ns->rdf( 'Property' ) ),
			$this->propertyQuad( new Iri( 'https://example.org/ns#seeAlso' ), new Iri( 'https://example.org/' ) ),
		);

		$this->assertCount( 1, ParsedRdfXml::canonicalTriples( $output ) );
	}

	public function testLeavingATripleOutLogsAWarningNamingItsSubject(): void {
		$this->serialize( $this->propertyQuad( $this->ns->rdfs( 'comment' ), RdfLiteralFactory::typed( "bell \x07", 'string' ) ) );

		$warnings = $this->logger->getLogCalls()->filter( static fn ( LogCall $call ): bool => $call->getLevel() === LogLevel::WARNING );

		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( self::PROPERTY, $warnings->getFirstCall()->getMessage() );
	}

	public function testDocumentWithoutTriplesHoldsNoTriples(): void {
		$this->assertSame( [], ParsedRdfXml::canonicalTriples( $this->serialize() ) );
	}

	private function serialize( Quad ...$quads ): string {
		return ( new RdfXmlSerializer( $this->ns, $this->logger ) )->serialize( new QuadList( ...$quads ) );
	}

	private function propertyQuad( Iri $predicate, RdfTerm $object ): Quad {
		return new Quad( new Iri( self::PROPERTY ), $predicate, $object, $this->ns->schemaGraph() );
	}

	private function typeQuad( Iri $subject, Iri $type ): Quad {
		return new Quad( $subject, $this->ns->rdfType(), $type, $this->ns->schemaGraph() );
	}

}
