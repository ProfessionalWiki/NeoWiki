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

}
