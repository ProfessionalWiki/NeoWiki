<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ExportSchemasRdfsApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdfXml;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Schemas are pages, so the tests save them in the database.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ExportSchemasRdfsApi
 * @group Database
 */
class ExportSchemasRdfsApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->createSchema( 'Person', '{"description":"","propertyDefinitions":{"Birth date":{"type":"date"}}}' );
	}

	/**
	 * @param array<string, string> $query
	 * @param array<string, string> $headers
	 */
	private function export( array $query = [], array $headers = [] ): Response {
		return $this->executeHandler(
			new ExportSchemasRdfsApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => $query,
				'headers' => $headers,
			] )
		);
	}

	private function domainTripleOf( string $schemaName, string $propertyName ): string {
		$ns = NeoWikiExtension::getInstance()->getRdfNamespaces();

		return implode( "\t", [
			$ns->property( new SchemaName( $schemaName ), $propertyName )->value,
			RdfNamespaces::RDFS . 'domain',
			$ns->schemaClass( new SchemaName( $schemaName ) )->value,
			'',
		] );
	}

	public function testExportsTheSchemasAsTurtleByDefault(): void {
		$response = $this->export();

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'text/turtle; charset=utf-8', $response->getHeaderLine( 'Content-Type' ) );
		$this->assertContains(
			$this->domainTripleOf( 'Person', 'Birth date' ),
			ParsedRdf::canonicalQuads( $response->getBody()->getContents() )
		);
	}

	public function testFormatParameterSelectsRdfXml(): void {
		$response = $this->export( query: [ 'format' => 'rdfxml' ] );

		$this->assertSame( 'application/rdf+xml; charset=utf-8', $response->getHeaderLine( 'Content-Type' ) );
		$this->assertContains(
			$this->domainTripleOf( 'Person', 'Birth date' ),
			ParsedRdfXml::canonicalTriples( $response->getBody()->getContents() )
		);
	}

	/**
	 * @dataProvider negotiationProvider
	 * @param array<string, string> $query
	 * @param array<string, string> $headers
	 */
	public function testPicksTheSerialization( array $query, array $headers, string $contentType ): void {
		$this->assertSame( $contentType, $this->export( $query, $headers )->getHeaderLine( 'Content-Type' ) );
	}

	public static function negotiationProvider(): iterable {
		yield 'Accept naming RDF/XML' => [ [], [ 'Accept' => 'application/rdf+xml' ], 'application/rdf+xml; charset=utf-8' ];
		yield 'Accept naming both' => [ [], [ 'Accept' => 'application/rdf+xml, text/turtle' ], 'text/turtle; charset=utf-8' ];
		yield 'format over Accept' => [ [ 'format' => 'turtle' ], [ 'Accept' => 'application/rdf+xml' ], 'text/turtle; charset=utf-8' ];
	}

	public function testExportVariesOnAcceptWhenTheHeaderPicksTheSerialization(): void {
		$this->assertSame( 'Accept', $this->export()->getHeaderLine( 'Vary' ) );
	}

	public function testExportDoesNotVaryWhenTheFormatParameterFixesTheSerialization(): void {
		$this->assertSame( '', $this->export( query: [ 'format' => 'turtle' ] )->getHeaderLine( 'Vary' ) );
	}

	/**
	 * @dataProvider downloadNameProvider
	 */
	public function testNamesTheDownloadAfterItsFormat( string $format, string $disposition ): void {
		$this->assertSame( $disposition, $this->export( query: [ 'format' => $format ] )->getHeaderLine( 'Content-Disposition' ) );
	}

	public static function downloadNameProvider(): iterable {
		yield 'Turtle' => [ 'turtle', 'inline; filename="schemas.ttl"' ];
		yield 'RDF/XML' => [ 'rdfxml', 'inline; filename="schemas.rdf"' ];
	}

	public function testExportsTheSchemasPastAPageWhoseTitleNamesNoSchema(): void {
		// A reserved title can be neither saved nor moved to, but an import can still leave one in the namespace.
		$this->createSchema( 'Placeholder' );
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'page' )
			->set( [ 'page_title' => 'Subject' ] )
			->where( [ 'page_namespace' => NeoWikiExtension::NS_SCHEMA, 'page_title' => 'Placeholder' ] )
			->caller( __METHOD__ )
			->execute();
		$this->createSchema( 'Venue', '{"description":"","propertyDefinitions":{"City":{"type":"text"}}}' );

		$triples = ParsedRdf::canonicalQuads( $this->export()->getBody()->getContents() );

		$this->assertContains( $this->domainTripleOf( 'Person', 'Birth date' ), $triples );
		$this->assertContains( $this->domainTripleOf( 'Venue', 'City' ), $triples );
	}

	public function testLeavesOutSchemasTheCallerMayNotRead(): void {
		$this->createSchema( 'Secret', '{"description":"","propertyDefinitions":{"Code":{"type":"text"}}}' );

		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );
		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function ( $title, $user, $action, &$result ): bool {
				if ( $action === 'read' && $title->getDBkey() === 'Secret' ) {
					$result = [ 'badaccess-group0' ];
					return false;
				}
				return true;
			}
		);

		$triples = ParsedRdf::canonicalQuads( $this->export()->getBody()->getContents() );

		$this->assertContains( $this->domainTripleOf( 'Person', 'Birth date' ), $triples );
		$this->assertNotContains( $this->domainTripleOf( 'Secret', 'Code' ), $triples );
	}

}
