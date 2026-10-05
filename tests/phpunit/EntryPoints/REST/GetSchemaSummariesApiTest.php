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

	public function testListsSchemasByName(): void {
		$this->createSchema( 'Zebra' );
		$this->createSchema( 'Ant' );
		$this->createSchema( 'Moth' );

		$this->assertSame( [ 'Ant', 'Moth', 'Zebra' ], $this->namesOf( $this->get( [] ) ) );
	}

	public function testFollowingTheCursorWalksAllPages(): void {
		$this->createSchema( 'Gamma' );
		$this->createSchema( 'Alpha' );
		$this->createSchema( 'Beta' );

		$firstPage = $this->get( [ 'limit' => '2' ] );

		$this->assertSame( [ 'Alpha', 'Beta' ], $this->namesOf( $firstPage ) );
		$this->assertIsString( $firstPage['nextCursor'] );

		$secondPage = $this->get( [ 'limit' => '2', 'cursor' => $firstPage['nextCursor'] ] );

		$this->assertSame( [ 'Gamma' ], $this->namesOf( $secondPage ) );
		$this->assertNull( $secondPage['nextCursor'] );
	}

	public function testExactPageBoundaryEndsPagination(): void {
		$this->createSchema( 'Alpha' );
		$this->createSchema( 'Beta' );

		$data = $this->get( [ 'limit' => '2' ] );

		$this->assertCount( 2, $data['schemas'] );
		$this->assertNull( $data['nextCursor'] );
	}

	public function testACursorAfterADeletedLastSchemaReturnsAnEmptyLastPage(): void {
		$this->createSchema( 'Alpha' );
		$this->createSchema( 'Beta' );
		$cursor = $this->get( [ 'limit' => '1' ] )['nextCursor'];
		$this->deletePageByName( 'Schema:Beta' );

		$data = $this->get( [ 'limit' => '1', 'cursor' => $cursor ] );

		$this->assertSame( [], $data['schemas'] );
		$this->assertNull( $data['nextCursor'] );
	}

	public function testListsOnlySchemasWhoseNameContainsTheSearch(): void {
		$this->createSchema( 'Artwork' );
		$this->createSchema( 'City' );
		$this->createSchema( 'Martial art' );

		$this->assertSame( [ 'Artwork', 'Martial art' ], $this->namesOf( $this->get( [ 'search' => 'art' ] ) ) );
	}

	public function testFollowingTheCursorKeepsToTheSearch(): void {
		$this->createSchema( 'Artwork' );
		$this->createSchema( 'Bridge' );
		$this->createSchema( 'Artist' );
		$cursor = $this->get( [ 'search' => 'Art', 'limit' => '1' ] )['nextCursor'];

		$this->assertSame( [ 'Artwork' ], $this->namesOf( $this->get( [ 'search' => 'Art', 'cursor' => $cursor ] ) ) );
	}

	/**
	 * @dataProvider malformedCursorProvider
	 */
	public function testRejectsMalformedCursor( string $cursor ): void {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->get( [ 'cursor' => $cursor ] );
	}

	public static function malformedCursorProvider(): iterable {
		yield 'not base64-encoded JSON' => [ 'not-a-cursor' ];
		yield 'JSON that is no name' => [ rtrim( base64_encode( '123' ), '=' ) ];
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

		$data = $this->get( [ 'limit' => '2' ] );

		$this->assertSame( [ 'ReadableSchema', 'TrailingSchema' ], $this->namesOf( $data ) );
		$this->assertNull( $data['nextCursor'] );
	}

	/**
	 * @param array<string, string> $queryParams
	 * @return array<string, mixed>
	 */
	private function get( array $queryParams ): array {
		return json_decode( $this->executeHandler(
			new GetSchemaSummariesApi(),
			new RequestData( [ 'method' => 'GET', 'queryParams' => $queryParams ] )
		)->getBody()->getContents(), true );
	}

	/**
	 * @param array<string, mixed> $response
	 * @return list<string>
	 */
	private function namesOf( array $response ): array {
		return array_column( $response['schemas'], 'name' );
	}

}
