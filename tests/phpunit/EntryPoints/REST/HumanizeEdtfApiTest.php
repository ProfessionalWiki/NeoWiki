<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\HumanizeEdtfApi;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\HumanizeEdtfApi
 * @group Database
 */
class HumanizeEdtfApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testRejectsRequestWithoutValueParam(): void {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			new HumanizeEdtfApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);
	}

	public function testReturnsHumanizedReading(): void {
		$response = $this->executeHandler(
			new HumanizeEdtfApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'value' => '1985?', 'language' => 'en' ],
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertSame( '1985?', $body['value'] );
		$this->assertNotNull( $body['humanized'] );
		$this->assertNotSame( '1985?', $body['humanized'] );
	}

	public function testReturnsNullHumanizedForInvalidValue(): void {
		$response = $this->executeHandler(
			new HumanizeEdtfApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'value' => 'not-a-date' ],
			] )
		);

		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertNull( $body['humanized'] );
	}

}
