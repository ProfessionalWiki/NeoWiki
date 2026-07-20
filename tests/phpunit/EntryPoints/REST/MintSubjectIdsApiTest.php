<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\SubjectIdMinter;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\MintSubjectIdsApi;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionIdGenerator;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\MintSubjectIdsApi
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectIdMinter
 */
class MintSubjectIdsApiTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	public function testReturnsRequestedNumberOfDistinctValidIds(): void {
		$response = $this->executeHandler( $this->newHandler(), $this->requestWithCount( 3 ) );

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertCount( 3, $body['subjectIds'] );
		$this->assertCount( 3, array_unique( $body['subjectIds'] ) );

		foreach ( $body['subjectIds'] as $id ) {
			$this->assertTrue( SubjectId::isValid( $id ), "Returned id '$id' is not a valid Subject ID" );
		}
	}

	public function testCountOfOneReturnsOneId(): void {
		$response = $this->executeHandler( $this->newHandler(), $this->requestWithCount( 1 ) );

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertCount( 1, $body['subjectIds'] );
	}

	public function testMaximumCountReturnsThatManyDistinctIds(): void {
		$response = $this->executeHandler( $this->newHandler(), $this->requestWithCount( 1000 ) );

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertCount( 1000, $body['subjectIds'] );
		$this->assertCount( 1000, array_unique( $body['subjectIds'] ) );
	}

	public function testZeroCountIsRejectedWith400(): void {
		$response = $this->executeHandler( $this->newHandler(), $this->requestWithCount( 0 ) );

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testCountAboveMaximumIsRejectedWith400(): void {
		$response = $this->executeHandler( $this->newHandler(), $this->requestWithCount( 1001 ) );

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testNeedsWriteAccessReturnsFalse(): void {
		$this->assertFalse( $this->newHandler()->needsWriteAccess() );
	}

	private function newHandler(): MintSubjectIdsApi {
		return new MintSubjectIdsApi( new SubjectIdMinter( new ProductionIdGenerator() ) );
	}

	private function requestWithCount( int $count ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'bodyContents' => json_encode( [ 'count' => $count ] ),
			'headers' => [ 'Content-Type' => 'application/json' ],
		] );
	}

}
