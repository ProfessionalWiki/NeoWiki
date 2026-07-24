<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\HttpException;
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
		$this->assertNull( $data['nextCursor'] );
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

		$this->assertCount( 2, $data['schemas'] );
		$this->assertNull( $data['nextCursor'] );

		$byName = [];
		foreach ( $data['schemas'] as $summary ) {
			$byName[$summary['name']] = $summary;
		}

		$this->assertSame( 'A company or organization', $byName['Company']['description'] );
		$this->assertSame( 2, $byName['Company']['propertyCount'] );

		$this->assertSame( 'A person', $byName['Person']['description'] );
		$this->assertSame( 1, $byName['Person']['propertyCount'] );
	}

	public function testFollowingTheCursorWalksAllPages(): void {
		$this->createSchema( 'Alpha', '{"title":"Alpha","description":"First","propertyDefinitions":{}}' );
		$this->createSchema( 'Beta', '{"title":"Beta","description":"Second","propertyDefinitions":{}}' );
		$this->createSchema( 'Gamma', '{"title":"Gamma","description":"Third","propertyDefinitions":{}}' );

		$firstPage = json_decode( $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2' ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [ 'Alpha', 'Beta' ], array_column( $firstPage['schemas'], 'name' ) );
		$this->assertIsString( $firstPage['nextCursor'] );

		$secondPage = json_decode( $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2', 'cursor' => $firstPage['nextCursor'] ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [ 'Gamma' ], array_column( $secondPage['schemas'], 'name' ) );
		$this->assertNull( $secondPage['nextCursor'] );
	}

	public function testExactPageBoundaryEndsPagination(): void {
		$this->createSchema( 'Alpha', '{"title":"Alpha","description":"First","propertyDefinitions":{}}' );
		$this->createSchema( 'Beta', '{"title":"Beta","description":"Second","propertyDefinitions":{}}' );

		$data = json_decode( $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2' ],
			] )
		)->getBody()->getContents(), true );

		$this->assertCount( 2, $data['schemas'] );
		$this->assertNull( $data['nextCursor'] );
	}

	/**
	 * @dataProvider cursorPastEndProvider
	 */
	public function testCursorPastTheEndReturnsAnEmptyLastPage( string $cursor ): void {
		$this->createSchema( 'Alpha', '{"title":"Alpha","description":"First","propertyDefinitions":{}}' );

		$data = json_decode( $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'cursor' => $cursor ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [], $data['schemas'] );
		$this->assertNull( $data['nextCursor'] );
	}

	public static function cursorPastEndProvider(): array {
		return [
			'past the last page id' => [ '999999' ],
			'beyond integer range (saturates)' => [ '99999999999999999999999' ],
		];
	}

	public function testRejectsMalformedCursor(): void {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'cursor' => 'not-a-cursor' ],
			] )
		);
	}

	public function testExcludesSchemasTheRequestUserCannotReadWithoutLeavingAGapInThePage(): void {
		// End-to-end guard for the #1062 count oracle: a Schema the request user may not read is
		// skipped and a readable one after it fills its slot, so the page carries no trace of the
		// restricted Schema. This exercises the handler's getRequestAuthority wiring, which the
		// persistence-layer tests bypass by injecting an authority directly.
		$this->createSchema( 'ReadableSchema', '{"title":"ReadableSchema","description":"","propertyDefinitions":{}}' );
		$this->createSchema( 'RestrictedSchema', '{"title":"RestrictedSchema","description":"","propertyDefinitions":{}}' );
		$this->createSchema( 'TrailingSchema', '{"title":"TrailingSchema","description":"","propertyDefinitions":{}}' );

		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );
		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function ( $title, $user, $action, &$result ): bool {
				if ( $action === 'read' && $title->getDBkey() === 'RestrictedSchema' ) {
					$result = [ 'badaccess-group0' ];
					return false;
				}
				return true;
			}
		);

		$data = json_decode( $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2' ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [ 'ReadableSchema', 'TrailingSchema' ], array_column( $data['schemas'], 'name' ) );
		$this->assertNull( $data['nextCursor'] );
	}

}
