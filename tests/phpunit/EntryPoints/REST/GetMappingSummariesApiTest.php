<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

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
		$this->assertSame( 0, $data['totalRows'] );
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

		$this->assertSame( 2, $data['totalRows'] );
		$this->assertCount( 2, $data['mappings'] );

		$byName = [];
		foreach ( $data['mappings'] as $summary ) {
			$byName[$summary['name']] = $summary;
		}

		$this->assertSame( [ 'Artist', 'Book', 'City' ], $byName['EDM']['schemas'] );
		$this->assertSame( [ 'Manuscript' ], $byName['Dublin Core']['schemas'] );
	}

	public function testPaginationLimitsResults(): void {
		$this->createMapping( 'Alpha', '{"version":1,"schemas":{}}' );
		$this->createMapping( 'Beta', '{"version":1,"schemas":{}}' );
		$this->createMapping( 'Gamma', '{"version":1,"schemas":{}}' );

		$response = $this->executeHandler(
			new GetMappingSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2', 'offset' => '0' ],
			] )
		);

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertCount( 2, $data['mappings'] );
		$this->assertSame( 3, $data['totalRows'] );
	}

	public function testPaginationOffset(): void {
		$this->createMapping( 'Alpha', '{"version":1,"schemas":{}}' );
		$this->createMapping( 'Beta', '{"version":1,"schemas":{}}' );
		$this->createMapping( 'Gamma', '{"version":1,"schemas":{}}' );

		$response = $this->executeHandler(
			new GetMappingSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2', 'offset' => '1' ],
			] )
		);

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertCount( 2, $data['mappings'] );
		$this->assertSame( 'Beta', $data['mappings'][0]['name'] );
		$this->assertSame( 'Gamma', $data['mappings'][1]['name'] );
		$this->assertSame( 3, $data['totalRows'] );
	}

}
