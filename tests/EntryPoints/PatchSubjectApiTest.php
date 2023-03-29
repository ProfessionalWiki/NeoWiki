<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\PatchSubjectApi
 * @group database
 */
class PatchSubjectApiTest extends \MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testSmoke(): void {
		$this->createPages();

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

	private function createPages(): void {
		$this->editPage(
			'PatchSubjectApiTest',
			SubjectContent::newFromSubjects( new SubjectMap(
				TestSubject::build(
					id: '123e4567-e89b-12d3-a456-426655440000',
					label: new SubjectLabel( 'Test subject 426655440000' ),
				)
			) )
		);
	}

}
