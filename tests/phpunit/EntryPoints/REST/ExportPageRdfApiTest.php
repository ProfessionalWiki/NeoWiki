<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ExportPageRdfApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ExportPageRdfApi
 * @group Database
 */
class ExportPageRdfApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	private const string SCHEMA = 'ExportPageRdfApiTestSchema';
	private const string SUBJECT_ID = 'sTestERA1111111';

	private int $pageId;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema(
			self::SCHEMA,
			<<<JSON
{
	"title": "ExportPageRdfApiTestSchema",
	"propertyDefinitions": {
		"population": { "type": "text" }
	}
}
JSON
		);

		$this->pageId = $this->createPageWithSubjects(
			'ExportPageRdfApiTest_Berlin',
			mainSubject: TestSubject::build(
				id: self::SUBJECT_ID,
				label: new SubjectLabel( 'Berlin' ),
				schemaName: new SchemaName( self::SCHEMA ),
				statements: new StatementList( [
					TestStatement::build( 'population', '3700000' ),
				] )
			),
		)->getPage()->getId();
	}

	/**
	 * @param array<string, string> $query
	 * @param array<string, string> $headers
	 */
	private function export( array $query = [], array $headers = [], ?int $pageId = null, ?Authority $authority = null ): Response {
		return $this->executeHandler(
			new ExportPageRdfApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)( $pageId ?? $this->pageId ) ],
				'queryParams' => $query,
				'headers' => $headers,
			] ),
			authority: $authority
		);
	}

	public function testDefaultsToTriGWithTheNativeProjectionsPageNamedGraph(): void {
		$response = $this->export();
		$body = $response->getBody()->getContents();

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'application/trig; charset=utf-8', $response->getHeaderLine( 'Content-Type' ) );
		$this->assertStringContainsString( 'Berlin', $body );
		$this->assertStringEndsWith(
			'/graph/native/page/' . $this->pageId,
			$this->soleGraphIn( $body ),
			'Every TriG quad belongs to the page named graph of the native projection.'
		);
	}

	public function testFormatParameterSelectsTurtleWithoutANamedGraph(): void {
		$response = $this->export( query: [ 'format' => 'turtle' ] );
		$body = $response->getBody()->getContents();

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'text/turtle; charset=utf-8', $response->getHeaderLine( 'Content-Type' ) );
		$this->assertStringContainsString( 'Berlin', $body );
		$this->assertSame( [ '' ], $this->graphsIn( $body ), 'Turtle drops the named graph.' );
	}

	public function testFormatParameterOverridesTheAcceptHeader(): void {
		$response = $this->export( query: [ 'format' => 'trig' ], headers: [ 'Accept' => 'text/turtle' ] );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'application/trig; charset=utf-8', $response->getHeaderLine( 'Content-Type' ) );
	}

	public function testAcceptHeaderSelectsTurtle(): void {
		$response = $this->export( headers: [ 'Accept' => 'text/turtle' ] );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'text/turtle; charset=utf-8', $response->getHeaderLine( 'Content-Type' ) );
		$this->assertSame( [ '' ], $this->graphsIn( $response->getBody()->getContents() ) );
	}

	public function testAcceptHeaderSelectsTriG(): void {
		$response = $this->export( headers: [ 'Accept' => 'application/trig' ] );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'application/trig; charset=utf-8', $response->getHeaderLine( 'Content-Type' ) );
	}

	public function testReturns404ForMissingPage(): void {
		$response = $this->export( pageId: 999999 );

		$this->assertSame( 404, $response->getStatusCode() );
	}

	public function testReturns404ForPageWithoutSubjectSlot(): void {
		$plainPage = $this->insertPage( 'ExportPageRdfApiTest_Plain', 'Just wikitext, no NeoWiki subjects.' );

		$response = $this->export( pageId: $plainPage['id'] );

		$this->assertSame( 404, $response->getStatusCode() );
	}

	public function testAbsentAndDeniedPagesProduceByteIdenticalResponses(): void {
		$plainPage = $this->insertPage( 'ExportPageRdfApiTest_PlainForByteIdentity', 'Just wikitext, no NeoWiki subjects.' );

		$absentResponse = $this->export( pageId: $plainPage['id'] );
		$deniedResponse = $this->export(
			pageId: $plainPage['id'],
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		$this->assertSame( $absentResponse->getStatusCode(), $deniedResponse->getStatusCode() );
		$this->assertSame(
			$absentResponse->getBody()->getContents(),
			$deniedResponse->getBody()->getContents()
		);
		$this->assertSame(
			$absentResponse->getHeaderLine( 'Content-Type' ),
			$deniedResponse->getHeaderLine( 'Content-Type' )
		);
	}

	public function testUnreadablePageIsIndistinguishableFromAPageWithoutData(): void {
		$response = $this->export( authority: $this->authorityWithGlobalReadButNoPageRead() );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertStringContainsString(
			'No NeoWiki data found for page: ' . $this->pageId,
			$response->getBody()->getContents()
		);
	}

	public function testPageReadableByANonUltimateAuthorityIsExported(): void {
		$response = $this->export(
			authority: $this->mockRegisteredAuthority(
				static fn ( string $permission ): bool => $permission === 'read'
			)
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testUnknownProjectionStillReturns400ForAnUnreadablePage(): void {
		// The projection check runs before the read gate, so a denied caller sees the same
		// 400 as anyone else and cannot use it to probe page readability.
		$response = $this->export(
			query: [ 'projection' => 'no-such-projection' ],
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testProjectionNativeIsTheDefaultAndUnchanged(): void {
		$default = $this->export();
		$explicit = $this->export( query: [ 'projection' => 'native' ] );

		$this->assertSame( 200, $explicit->getStatusCode() );
		$this->assertSame(
			ParsedRdf::canonicalQuads( $default->getBody()->getContents() ),
			ParsedRdf::canonicalQuads( $explicit->getBody()->getContents() )
		);
	}

	public function testUnknownProjectionReturns400(): void {
		// No Mapping page declares "edm", so it is not a known projection.
		$response = $this->export( query: [ 'projection' => 'edm' ] );

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testValidTargetProjectsTheMappedVocabularyWithNativeSubjectIris(): void {
		$this->createBerlinToEdmMapping();

		$response = $this->export( query: [ 'projection' => 'edm', 'format' => 'turtle' ] );
		$body = $response->getBody()->getContents();

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertStringContainsString( 'europeana.eu/schemas/edm', $body, 'The mapped ontology vocabulary is used.' );
		$this->assertStringContainsString( self::SUBJECT_ID, $body, 'The Subject IRI stays native.' );
		$this->assertStringNotContainsString( self::SCHEMA, $body, 'No native schema class is emitted.' );
	}

	/**
	 * The export surface is where the projection name selected by the request reaches the projector that
	 * mints the graph (#1053). Asserting the graph IRI here, rather than only on the projector, is what
	 * catches that resolution handing the projector the wrong name.
	 */
	public function testOntologyProjectionPlacesItsQuadsInTheTargetsOwnPageNamedGraph(): void {
		$this->createBerlinToEdmMapping();

		$response = $this->export( query: [ 'projection' => 'edm' ] );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertStringEndsWith(
			'/graph/edm/page/' . $this->pageId,
			$this->soleGraphIn( $response->getBody()->getContents() ),
			'The ontology projection writes the target\'s own named graph, not the native one, so a store can hold both.'
		);
	}

	private function createBerlinToEdmMapping(): void {
		$this->createMapping( 'BerlinToEdm', <<<JSON
			{
				"version": 1,
				"schema": "{$this->schemaName()}",
				"target": "edm",
				"prefixes": {
					"edm": "http://www.europeana.eu/schemas/edm/",
					"dc": "http://purl.org/dc/elements/1.1/"
				},
				"subject": { "class": "edm:Place" },
				"properties": {
					"population": { "predicate": "dc:description" }
				}
			}
			JSON );
	}

	private function schemaName(): string {
		return self::SCHEMA;
	}

	/**
	 * The single named graph every quad in the document belongs to.
	 */
	private function soleGraphIn( string $rdf ): string {
		$graphs = $this->graphsIn( $rdf );

		$this->assertCount( 1, $graphs, 'A projection places every quad in exactly one named graph.' );

		return $graphs[0];
	}

	/**
	 * The graph value (the fourth field of each canonical quad) of every quad in the parsed document.
	 * Distinguishes TriG (a non-empty page graph on every quad) from Turtle (the empty default graph).
	 *
	 * @return list<string>
	 */
	private function graphsIn( string $rdf ): array {
		return array_values( array_unique( array_map(
			static fn ( string $line ): string => explode( "\t", $line )[3],
			ParsedRdf::canonicalQuads( $rdf )
		) ) );
	}

}
