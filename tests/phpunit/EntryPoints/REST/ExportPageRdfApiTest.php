<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

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

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ExportPageRdfApi
 * @group Database
 */
class ExportPageRdfApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

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
	private function export( array $query = [], array $headers = [], ?int $pageId = null ): Response {
		return $this->executeHandler(
			new ExportPageRdfApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)( $pageId ?? $this->pageId ) ],
				'queryParams' => $query,
				'headers' => $headers,
			] )
		);
	}

	public function testDefaultsToTriGWithThePageNamedGraph(): void {
		$response = $this->export();
		$body = $response->getBody()->getContents();

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'application/trig; charset=utf-8', $response->getHeaderLine( 'Content-Type' ) );
		$this->assertStringContainsString( 'Berlin', $body );
		$this->assertNotContains( '', $this->graphsIn( $body ), 'Every TriG quad belongs to the page named graph.' );
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
