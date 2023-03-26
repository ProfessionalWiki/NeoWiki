<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyQueryStore;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\PatchSubjectApi
 */
class PatchSubjectApiTest extends \MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testSmoke(): void {
		// TODO: environment for integration tests should do this by default
		NeoWikiExtension::getInstance()->setQueryStore( new SpyQueryStore() );
		NeoWikiExtension::getInstance()->setSubjectRepository( new InMemorySubjectRepository() );

		$response = $this->executeHandler(
			NeoWikiExtension::getInstance()->newPatchSubjectApi(),
			new RequestData( [
				'method' => 'PATCH',
				'pathParams' => [
					'subjectId' => '123e4567-e89b-12d3-a456-426655440000'
				],
				'bodyContents' => json_encode( [
					'properties' => [
						'animal' => 'bunny',
						'fluff' => 9001,
					]
				] ),
				'headers' => [
					'Content-Type' => 'application/json'
				]
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

}
