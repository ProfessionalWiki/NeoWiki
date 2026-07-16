<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Persistence;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfProjection;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\CallbackProjectionResolver;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\UnknownProjectionException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\ProjectionResolver;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\SparqlProjectionStore;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\CapturingSparqlUpdateEndpoint;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedPageProjector;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\SparqlProjectionStore
 */
class SparqlProjectionStoreTest extends TestCase {

	private const string BASE = 'https://wiki.example';
	private const string ENDPOINT = 'https://qlever.example/api/neowiki';

	private RdfNamespaces $ns;
	private CapturingSparqlUpdateEndpoint $endpoint;

	protected function setUp(): void {
		$this->ns = new RdfNamespaces( self::BASE );
		$this->endpoint = new CapturingSparqlUpdateEndpoint();
	}

	public function testSavePersistsDropThenInsertDataForThePageGraph(): void {
		$this->newStore( $this->resolverReturning( $this->personQuads() ), 'native' )->savePage( $this->page( 42 ) );

		$expected = "DROP SILENT GRAPH <https://wiki.example/page/42> ;\n"
			. "INSERT DATA { GRAPH <https://wiki.example/page/42> {\n"
			. "<https://wiki.example/entity/s1demo8aaaaaab5> a <https://wiki.example/schema/Person>;\n"
			. "    <http://www.w3.org/2000/01/rdf-schema#label> \"John Doe\".\n"
			. "} }";

		$this->assertSame( $expected, $this->endpoint->lastUpdate() );
	}

	public function testSaveOfEmptyProjectionSendsDropOnly(): void {
		$this->newStore( $this->resolverReturning( new QuadList() ), 'native' )->savePage( $this->page( 42 ) );

		$this->assertSame( 'DROP SILENT GRAPH <https://wiki.example/page/42>', $this->endpoint->lastUpdate() );
	}

	public function testDeleteSendsDropOnly(): void {
		$this->newStore( $this->resolverReturning( $this->personQuads() ), 'native' )->deletePage( new PageId( 42 ) );

		$this->assertSame( 'DROP SILENT GRAPH <https://wiki.example/page/42>', $this->endpoint->lastUpdate() );
	}

	public function testMaliciousLiteralIsEscapedAndStaysInsideOneInsertDataBlock(): void {
		$payload = "ev\"il\n} } ; DROP GRAPH <x>";
		$quads = new QuadList(
			new Quad(
				$this->ns->subject( new SubjectId( 's1demo8aaaaaab5' ) ),
				$this->ns->rdfsLabel(),
				new Literal( $payload, $this->ns->xsd( 'string' ) ),
				$this->ns->page( new PageId( 42 ) )
			)
		);

		$this->newStore( $this->resolverReturning( $quads ), 'native' )->savePage( $this->page( 42 ) );
		$update = $this->endpoint->lastUpdate();

		// Exactly one INSERT DATA block: the payload cannot open a second operation.
		$this->assertSame( 1, substr_count( $update, 'INSERT DATA' ) );

		// The embedded quote and newline are escaped, so the payload stays one literal.
		$this->assertStringContainsString( 'ev\\"il\\n} } ; DROP GRAPH <x>', $update );
		// A raw (unescaped) newline before the braces would mean a break-out; it must not be present.
		$this->assertStringNotContainsString( "il\n} }", $update );

		// The store embeds exactly the serializer's escaped output, and parsing that back yields one
		// triple: proof the escaping is real, not a truncation. (If hardf ever stops escaping literals,
		// this triple count breaks, flagging a pre-existing export bug.)
		$turtle = ( new HardfRdfSerializer( [] ) )->serialize( $quads, RdfFormat::Turtle );
		$this->assertStringContainsString( $turtle, $update );
		$this->assertCount( 1, ParsedRdf::canonicalQuads( $turtle ) );
	}

	public function testSaveWithUnknownProjectionThrowsNamingEndpointProjectionAndKnownNames(): void {
		$store = $this->newStore( $this->unknownProjectionResolver( [ 'native', 'edm' ] ), 'bogus' );

		try {
			$store->savePage( $this->page( 42 ) );
			$this->fail( 'Expected UnknownProjectionException' );
		} catch ( UnknownProjectionException $exception ) {
			$this->assertStringContainsString( self::ENDPOINT, $exception->getMessage() );
			$this->assertStringContainsString( 'bogus', $exception->getMessage() );
			$this->assertStringContainsString( 'native', $exception->getMessage() );
			$this->assertStringContainsString( 'edm', $exception->getMessage() );
		}

		$this->assertSame( [], $this->endpoint->updates, 'no update should be posted for an unknown projection' );
	}

	public function testDeleteWorksEvenWhenProjectionIsUnknown(): void {
		$this->newStore( $this->unknownProjectionResolver( [ 'native' ] ), 'bogus' )->deletePage( new PageId( 42 ) );

		$this->assertSame( 'DROP SILENT GRAPH <https://wiki.example/page/42>', $this->endpoint->lastUpdate() );
	}

	private function newStore( ProjectionResolver $resolver, string $projectionName ): SparqlProjectionStore {
		return new SparqlProjectionStore(
			endpoint: $this->endpoint,
			projectionResolver: $resolver,
			namespaces: $this->ns,
			serializer: new HardfRdfSerializer( [] ),
			projectionName: $projectionName,
			endpointUrl: self::ENDPOINT,
		);
	}

	private function resolverReturning( QuadList $quads ): ProjectionResolver {
		return new CallbackProjectionResolver(
			// The projection's serializer is unused by the store (it uses its own prefix-less one), but
			// RdfProjection requires one.
			fn ( string $name ): ?RdfProjection => new RdfProjection(
				new FixedPageProjector( $quads ),
				new HardfRdfSerializer( $this->ns->prefixMap() )
			),
			fn (): array => [ 'native' ]
		);
	}

	private function unknownProjectionResolver( array $knownNames ): ProjectionResolver {
		return new CallbackProjectionResolver(
			fn ( string $name ): ?RdfProjection => null,
			fn (): array => $knownNames
		);
	}

	private function page( int $id ): Page {
		return new Page( new PageId( $id ), new PageProperties(), PageSubjects::newEmpty() );
	}

	private function personQuads(): QuadList {
		$graph = $this->ns->page( new PageId( 42 ) );
		$subject = $this->ns->subject( new SubjectId( 's1demo8aaaaaab5' ) );

		return QuadList::fromArray( [
			new Quad( $subject, $this->ns->rdfType(), $this->ns->schemaClass( new SchemaName( 'Person' ) ), $graph ),
			new Quad( $subject, $this->ns->rdfsLabel(), new Literal( 'John Doe', $this->ns->xsd( 'string' ) ), $graph ),
		] );
	}

}
