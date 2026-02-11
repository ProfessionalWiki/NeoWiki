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

	public function testReturnsEmptyArrayWhenNoSchemas(): void {
		$response = $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertJsonStringEqualsJsonString( '[]', $response->getBody()->getContents() );
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

		/** @var array<int, array{name: string, description: string, propertyCount: int}> $data */
		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertCount( 2, $data );

		$byName = [];
		foreach ( $data as $summary ) {
			$byName[$summary['name']] = $summary;
		}

		$this->assertSame( 'A company or organization', $byName['Company']['description'] );
		$this->assertSame( 2, $byName['Company']['propertyCount'] );

		$this->assertSame( 'A person', $byName['Person']['description'] );
		$this->assertSame( 1, $byName['Person']['propertyCount'] );
	}

}
