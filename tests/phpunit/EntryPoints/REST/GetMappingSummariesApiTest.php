<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetMappingSummariesApi;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetMappingSummariesApi
 * @group Database
 */
class GetMappingSummariesApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testReturnsEmptyResultWhenNoMappings(): void {
		$response = $this->executeHandler(
			new GetMappingSummariesApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( [], $data['mappings'] );
		$this->assertNull( $data['nextCursor'] );
	}

	public function testReturnsMappingSummariesWithAlphabeticallySortedMappedSchemaNames(): void {
		// The schema keys are stored in a deliberately unsorted order (City, Artist, Book) so the
		// assertion below fails unless the API sorts them (Artist, Book, City).
		$this->createMapping( 'EDM', <<<JSON
{
	"version": 1,
	"prefixes": { "edm": "http://www.europeana.eu/schemas/edm/" },
	"schemas": {
		"City": { "subject": { "class": "edm:Place" }, "properties": {} },
		"Artist": { "subject": { "class": "edm:Agent" }, "properties": {} },
		"Book": { "subject": { "class": "edm:ProvidedCHO" }, "properties": {} }
	}
}
JSON
		);

		$this->createMapping( 'Dublin Core', <<<JSON
{
	"version": 1,
	"prefixes": { "dcterms": "http://purl.org/dc/terms/" },
	"schemas": {
		"Manuscript": { "subject": { "class": "dcterms:BibliographicResource" }, "properties": {} }
	}
}
JSON
		);

		$response = $this->executeHandler(
			new GetMappingSummariesApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertCount( 2, $data['mappings'] );
		$this->assertNull( $data['nextCursor'] );

		$byName = [];
		foreach ( $data['mappings'] as $summary ) {
			$byName[$summary['name']] = $summary;
		}

		$this->assertSame( [ 'Artist', 'Book', 'City' ], $byName['EDM']['schemas'] );
		$this->assertSame( [ 'Manuscript' ], $byName['Dublin Core']['schemas'] );
	}

	public function testFollowingTheCursorWalksAllPages(): void {
		$this->createMapping( 'Alpha', '{"version":1,"schemas":{}}' );
		$this->createMapping( 'Beta', '{"version":1,"schemas":{}}' );
		$this->createMapping( 'Gamma', '{"version":1,"schemas":{}}' );

		$firstPage = json_decode( $this->executeHandler(
			new GetMappingSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2' ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [ 'Alpha', 'Beta' ], array_column( $firstPage['mappings'], 'name' ) );
		$this->assertIsString( $firstPage['nextCursor'] );

		$secondPage = json_decode( $this->executeHandler(
			new GetMappingSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2', 'cursor' => $firstPage['nextCursor'] ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [ 'Gamma' ], array_column( $secondPage['mappings'], 'name' ) );
		$this->assertNull( $secondPage['nextCursor'] );
	}

	public function testRejectsMalformedCursor(): void {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			new GetMappingSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'cursor' => 'not-a-cursor' ],
			] )
		);
	}

	public function testExcludesMappingsTheRequestUserCannotReadWithoutLeavingAGapInThePage(): void {
		// End-to-end guard for the #1062 count oracle: a Mapping the request user may not read is
		// skipped and a readable one after it fills its slot, so the page carries no trace of the
		// restricted Mapping. This exercises the handler's getRequestAuthority wiring, which the
		// persistence-layer tests bypass by injecting an authority directly.
		$this->createMapping( 'ReadableMapping', '{"version":1,"schemas":{}}' );
		$this->createMapping( 'RestrictedMapping', '{"version":1,"schemas":{}}' );
		$this->createMapping( 'TrailingMapping', '{"version":1,"schemas":{}}' );

		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );
		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function ( $title, $user, $action, &$result ): bool {
				if ( $action === 'read' && $title->getDBkey() === 'RestrictedMapping' ) {
					$result = [ 'badaccess-group0' ];
					return false;
				}
				return true;
			}
		);

		$data = json_decode( $this->executeHandler(
			new GetMappingSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2' ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [ 'ReadableMapping', 'TrailingMapping' ], array_column( $data['mappings'], 'name' ) );
		$this->assertNull( $data['nextCursor'] );
	}

	public function testUnloadableMappingDoesNotConsumePageSpace(): void {
		// A readable Mapping whose stored JSON cannot be parsed into a Mapping (here it is missing the
		// required "schemas" key) is skipped by the summary loader, and a readable Mapping after it
		// fills the freed slot, so the page still returns a full $limit items and reports no more to
		// come. Such a page is reachable in production through XML import, which bypasses
		// MappingContentHandler::validateSave (#1022).
		$this->createMappingsWithUnloadableMiddle();

		$page = $this->requestMappings( [ 'limit' => '2' ] );

		$this->assertSame( [ 'Alpha', 'Gamma' ], array_column( $page['mappings'], 'name' ) );
		$this->assertNull( $page['nextCursor'] );
	}

	public function testWalkingPastAnUnloadableMappingNeverYieldsAnEmptyPage(): void {
		// Walked one item at a time, the unloadable Mapping is skipped inside the page that reaches it
		// rather than served as an empty page with a follow-up cursor: it never appears, and no page in
		// the walk comes back empty while items still remain.
		$this->createMappingsWithUnloadableMiddle();

		$firstPage = $this->requestMappings( [ 'limit' => '1' ] );

		$this->assertSame( [ 'Alpha' ], array_column( $firstPage['mappings'], 'name' ) );
		$this->assertIsString( $firstPage['nextCursor'] );

		$secondPage = $this->requestMappings( [ 'limit' => '1', 'cursor' => $firstPage['nextCursor'] ] );

		$this->assertSame( [ 'Gamma' ], array_column( $secondPage['mappings'], 'name' ) );
		$this->assertNull( $secondPage['nextCursor'] );
	}

	/**
	 * @return array{mappings: list<array{name: string, schemas: list<string>}>, nextCursor: ?string}
	 */
	private function requestMappings( array $queryParams ): array {
		return json_decode(
			$this->executeHandler(
				new GetMappingSummariesApi(),
				new RequestData( [ 'method' => 'GET', 'queryParams' => $queryParams ] )
			)->getBody()->getContents(),
			true
		);
	}

	/**
	 * Creates three Mappings in page-ID order — Alpha, an unloadable Beta, then Gamma — so a page
	 * request has to skip a readable-but-unparseable Mapping sitting between two good ones. Beta's dump
	 * is derived from Alpha (a real list member) so no extra page pollutes the listing, and its content
	 * is broken by dropping the required "schemas" key. It goes in through XML import because
	 * MappingContentHandler::validateSave would reject such content on the edit path (#1022).
	 */
	private function createMappingsWithUnloadableMiddle(): void {
		$this->markPageTableAsUsed();
		$this->createMapping( 'Alpha', '{"version":1,"schemas":{}}' );

		$xml = $this->exportPageToXml( 'Mapping:Alpha' );
		$xml = str_replace( 'Mapping:Alpha', 'Mapping:Beta', $xml );
		$xml = str_replace( '"schemas"', '"schemaX"', $xml );
		$this->importXml( $xml );

		$this->createMapping( 'Gamma', '{"version":1,"schemas":{}}' );
	}

}
