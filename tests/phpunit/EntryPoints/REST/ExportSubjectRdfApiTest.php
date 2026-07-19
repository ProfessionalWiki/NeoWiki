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
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ExportSubjectRdfApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\Domain\Rdf\ParsedRdf;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ExportSubjectRdfApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\RdfFormatNegotiation
 * @group Database
 */
class ExportSubjectRdfApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	private const string SCHEMA = 'ExportSubjectRdfApiTestSchema';
	private const string BERLIN_ID = 'sTestSRA1111111';
	private const string PARIS_ID = 'sTestSRA2222222';
	private const string UNMAPPED_ID = 'sTestSRA3333333';
	private const string ABSENT_ID = 'sTestSRA9999999';

	private int $pageId;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema(
			self::SCHEMA,
			<<<JSON
{
	"title": "ExportSubjectRdfApiTestSchema",
	"propertyDefinitions": {
		"population": { "type": "text" }
	}
}
JSON
		);

		$this->pageId = $this->createPageWithSubjects(
			'ExportSubjectRdfApiTest_Cities',
			mainSubject: TestSubject::build(
				id: self::BERLIN_ID,
				label: new SubjectLabel( 'Berlin' ),
				schemaName: new SchemaName( self::SCHEMA ),
				statements: new StatementList( [
					TestStatement::build( 'population', '3700000' ),
				] )
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: self::PARIS_ID,
					label: new SubjectLabel( 'Paris' ),
					schemaName: new SchemaName( self::SCHEMA ),
					statements: new StatementList( [
						TestStatement::build( 'population', '2100000' ),
					] )
				)
			),
		)->getPage()->getId();
	}

	/**
	 * @param array<string, string> $query
	 * @param array<string, string> $headers
	 */
	private function export( array $query = [], array $headers = [], ?string $subjectId = null, ?Authority $authority = null ): Response {
		return $this->executeHandler(
			new ExportSubjectRdfApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'subjectId' => $subjectId ?? self::BERLIN_ID ],
				'queryParams' => $query,
				'headers' => $headers,
			] ),
			authority: $authority
		);
	}

	public function testDefaultsToTriGWithTheHostingPagesNamedGraph(): void {
		$response = $this->export();
		$body = $response->getBody()->getContents();

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'application/trig; charset=utf-8', $response->getHeaderLine( 'Content-Type' ) );
		$this->assertStringContainsString( 'Berlin', $body );
		$this->assertStringEndsWith(
			'/graph/native/page/' . $this->pageId,
			$this->soleGraphIn( $body ),
			'Every TriG quad belongs to the hosting page\'s named graph.'
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

	public function testReturns404ForAnUnknownSubject(): void {
		$response = $this->export( subjectId: self::ABSENT_ID );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertStringContainsString(
			'No NeoWiki data found for subject: ' . self::ABSENT_ID,
			$response->getBody()->getContents()
		);
	}

	public function testSubjectOnAnUnreadablePageIsByteIdenticalToAnAbsentSubject(): void {
		// An existing Subject whose hosting page the caller cannot read must answer exactly like an
		// absent Subject, so the endpoint cannot confirm a harvested Subject id exists (#1046). Only the
		// Subject id echoed in the message differs between the two.
		$denied = $this->export( authority: $this->authorityWithGlobalReadButNoPageRead() );
		$absent = $this->export( subjectId: self::ABSENT_ID );

		$this->assertSame( 404, $denied->getStatusCode() );
		$this->assertSame( $absent->getStatusCode(), $denied->getStatusCode() );
		$this->assertSame(
			$absent->getHeaderLine( 'Content-Type' ),
			$denied->getHeaderLine( 'Content-Type' )
		);
		$this->assertSame(
			str_replace( self::ABSENT_ID, self::BERLIN_ID, $absent->getBody()->getContents() ),
			$denied->getBody()->getContents()
		);
	}

	public function testUnknownProjectionReturns400EvenForAnAbsentSubject(): void {
		// The projection check runs before the Subject is resolved, so a caller cannot use a valid vs
		// invalid projection to probe whether a Subject exists.
		$response = $this->export(
			query: [ 'projection' => 'no-such-projection' ],
			subjectId: self::ABSENT_ID
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testReturns400ForAMalformedSubjectId(): void {
		$response = $this->export( subjectId: 'not-a-valid-id' );

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testOmittedProjectionEqualsExplicitNative(): void {
		$default = $this->export();
		$explicit = $this->export( query: [ 'projection' => 'native' ] );

		$this->assertSame( 200, $explicit->getStatusCode() );
		$this->assertSame(
			ParsedRdf::canonicalQuads( $default->getBody()->getContents() ),
			ParsedRdf::canonicalQuads( $explicit->getBody()->getContents() )
		);
	}

	public function testValidTargetProjectsTheMappedVocabularyWithNativeSubjectIris(): void {
		$this->createBerlinToEdmMapping();

		$response = $this->export( query: [ 'projection' => 'edm', 'format' => 'turtle' ] );
		$body = $response->getBody()->getContents();

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertStringContainsString( 'europeana.eu/schemas/edm', $body, 'The mapped ontology vocabulary is used.' );
		$this->assertStringContainsString( self::BERLIN_ID, $body, 'The Subject IRI stays native.' );
		$this->assertStringNotContainsString( self::SCHEMA, $body, 'No native schema class is emitted.' );
	}

	public function testReadableSubjectWithoutAMappingForTheTargetReturnsAnEmptyGraph(): void {
		// "edm" is a known projection because Berlin's Schema is mapped to it, but this Subject's Schema
		// is not, so its ontology projection is empty. A readable Subject is never hidden behind a 404,
		// so the response is a 200 empty graph rather than a not-found.
		$this->createBerlinToEdmMapping();

		$this->createSchema( 'ExportSubjectRdfApiTestUnmappedSchema' );
		$this->createPageWithSubjects(
			'ExportSubjectRdfApiTest_Unmapped',
			mainSubject: TestSubject::build(
				id: self::UNMAPPED_ID,
				label: new SubjectLabel( 'Freetown' ),
				schemaName: new SchemaName( 'ExportSubjectRdfApiTestUnmappedSchema' )
			)
		);

		$response = $this->export( query: [ 'projection' => 'edm', 'format' => 'turtle' ], subjectId: self::UNMAPPED_ID );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame(
			[],
			ParsedRdf::canonicalQuads( $response->getBody()->getContents() ),
			'A readable Subject with no Mapping for the target projects to an empty graph, not a 404.'
		);
	}

	public function testReturnsOnlyTheRequestedSubjectsTriples(): void {
		$body = $this->export()->getBody()->getContents();

		$this->assertStringContainsString( 'Berlin', $body, 'The requested Subject is present.' );
		$this->assertStringNotContainsString( 'Paris', $body, 'A sibling Subject on the same page is not included.' );
		$this->assertStringNotContainsString( self::PARIS_ID, $body, 'A sibling Subject IRI is not included.' );
	}

	private function createBerlinToEdmMapping(): void {
		$this->createMapping( 'ExportSubjectRdfApiTestBerlinToEdm', <<<JSON
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

		$this->assertCount( 1, $graphs, 'A per-Subject projection places every quad in exactly one named graph.' );

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
