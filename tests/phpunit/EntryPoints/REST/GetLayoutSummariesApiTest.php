<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetLayoutSummariesApi;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetLayoutSummariesApi
 * @group Database
 */
class GetLayoutSummariesApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testReturnsEmptyResultWhenNoLayouts(): void {
		$response = $this->executeHandler(
			new GetLayoutSummariesApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( [], $data['layouts'] );
		$this->assertNull( $data['nextCursor'] );
	}

	public function testReturnsLayoutSummaries(): void {
		$this->createLayout(
			'Company card',
			'{ "schema": "Company", "type": "infobox", "description": "A company layout", "displayRules": [] }'
		);
		$this->createLayout(
			'Person card',
			'{ "schema": "Person", "type": "infobox", "description": "A person layout", "displayRules": [] }'
		);

		$response = $this->executeHandler(
			new GetLayoutSummariesApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		$data = json_decode( $response->getBody()->getContents(), true );

		$this->assertCount( 2, $data['layouts'] );
		$this->assertNull( $data['nextCursor'] );

		$byName = [];
		foreach ( $data['layouts'] as $summary ) {
			$byName[$summary['name']] = $summary;
		}

		$this->assertSame( 'Company', $byName['Company card']['schema'] );
		$this->assertSame( 'infobox', $byName['Company card']['type'] );
		$this->assertSame( 'A company layout', $byName['Company card']['description'] );
		$this->assertSame( 0, $byName['Company card']['ruleCount'] );
	}

	public function testFollowingTheCursorWalksAllPages(): void {
		$this->createLayout( 'Alpha', '{ "schema": "Alpha", "type": "infobox" }' );
		$this->createLayout( 'Beta', '{ "schema": "Beta", "type": "infobox" }' );
		$this->createLayout( 'Gamma', '{ "schema": "Gamma", "type": "infobox" }' );

		$firstPage = json_decode( $this->executeHandler(
			new GetLayoutSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2' ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [ 'Alpha', 'Beta' ], array_column( $firstPage['layouts'], 'name' ) );
		$this->assertIsString( $firstPage['nextCursor'] );

		$secondPage = json_decode( $this->executeHandler(
			new GetLayoutSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2', 'cursor' => $firstPage['nextCursor'] ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [ 'Gamma' ], array_column( $secondPage['layouts'], 'name' ) );
		$this->assertNull( $secondPage['nextCursor'] );
	}

	public function testRejectsMalformedCursor(): void {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			new GetLayoutSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'cursor' => 'not-a-cursor' ],
			] )
		);
	}

	public function testExcludesLayoutsTheRequestUserCannotReadWithoutLeavingAGapInThePage(): void {
		// End-to-end guard for the #1062 count oracle: a Layout the request user may not read is
		// skipped and a readable one after it fills its slot, so the page carries no trace of the
		// restricted Layout. This exercises the handler's getRequestAuthority wiring, which the
		// persistence-layer tests bypass by injecting an authority directly.
		$this->createLayout( 'ReadableLayout', '{ "schema": "ReadableLayout", "type": "infobox" }' );
		$this->createLayout( 'RestrictedLayout', '{ "schema": "RestrictedLayout", "type": "infobox" }' );
		$this->createLayout( 'TrailingLayout', '{ "schema": "TrailingLayout", "type": "infobox" }' );

		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );
		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function ( $title, $user, $action, &$result ): bool {
				if ( $action === 'read' && $title->getDBkey() === 'RestrictedLayout' ) {
					$result = [ 'badaccess-group0' ];
					return false;
				}
				return true;
			}
		);

		$data = json_decode( $this->executeHandler(
			new GetLayoutSummariesApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'limit' => '2' ],
			] )
		)->getBody()->getContents(), true );

		$this->assertSame( [ 'ReadableLayout', 'TrailingLayout' ], array_column( $data['layouts'], 'name' ) );
		$this->assertNull( $data['nextCursor'] );
	}

}
