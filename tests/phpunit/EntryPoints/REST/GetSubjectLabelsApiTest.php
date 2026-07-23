<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectLabelsApi;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectLabelsApi
 * @group Database
 */
class GetSubjectLabelsApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testRejectsLimitAboveMaximum(): void {
		// The endpoint runs a per-result read-permission check, so an uncapped limit is an
		// unbounded-work vector. The cap is enforced before the handler runs any query (#1060).
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			new GetSubjectLabelsApi(),
			new RequestData( [
				'method' => 'GET',
				'queryParams' => [ 'schema' => 'Person', 'limit' => '100000' ],
			] )
		);
	}

}
