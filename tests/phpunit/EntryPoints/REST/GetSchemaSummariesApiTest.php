<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaSummariesApi;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaSummariesApi
 * @group Database
 */
class GetSchemaSummariesApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testReturnsEmptyResultWhenNoSchemas(): void {
		$response = $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( [], $data['schemas'] );
		$this->assertSame( 0, $data['totalRows'] );
	}

	public function testReturnsSchemaSummaries(): void {
		$this->createSchema( 'Company', <<<JSON
{
	"title": "Company",
	"description": "A company or organization",
	"propertyDefinitions": {
		"Name": { "type": "text" },
		"Website": { "type": "url" }
	}
}
JSON
		);

		$this->createSchema( 'Person', <<<JSON
{
	"title": "Person",
	"description": "A person",
	"propertyDefinitions": {
		"Age": { "type": "number" }
	}
}
JSON
		);

		$response = $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 2, $data['totalRows'] );
		$this->assertCount( 2, $data['schemas'] );

		$byName = [];
		foreach ( $data['schemas'] as $summary ) {
			$byName[$summary['name']] = $summary;
		}

		$this->assertSame( 'A company or organization', $byName['Company']['description'] );
		$this->assertSame( 2, $byName['Company']['propertyCount'] );

		$this->assertSame( 'A person', $byName['Person']['description'] );
		$this->assertSame( 1, $byName['Person']['propertyCount'] );
	}

	public function testPaginationLimitsResults(): void {
		$this->createSchema( 'Alpha', '{"title":"Alpha","description":"First","propertyDefinitions":{}}' );
		$this->createSchema( 'Beta', '{"title":"Beta","description":"Second","propertyDefinitions":{}}' );
		$this->createSchema( 'Gamma', '{"title":"Gamma","description":"Third","propertyDefinitions":{}}' );

		$response = $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2', 'offset' => '0' ],
			] )
		);

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertCount( 2, $data['schemas'] );
		$this->assertSame( 3, $data['totalRows'] );
	}

	public function testPaginationOffset(): void {
		$this->createSchema( 'Alpha', '{"title":"Alpha","description":"First","propertyDefinitions":{}}' );
		$this->createSchema( 'Beta', '{"title":"Beta","description":"Second","propertyDefinitions":{}}' );
		$this->createSchema( 'Gamma', '{"title":"Gamma","description":"Third","propertyDefinitions":{}}' );

		$response = $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2', 'offset' => '1' ],
			] )
		);

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertCount( 2, $data['schemas'] );
		$this->assertSame( 'Beta', $data['schemas'][0]['name'] );
		$this->assertSame( 'Gamma', $data['schemas'][1]['name'] );
		$this->assertSame( 3, $data['totalRows'] );
	}

}
