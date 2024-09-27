<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\PatchSubjectApi;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\PatchSubjectApi
 * @group Database
 */
class PatchSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use MockAuthorityTrait;

	public function testSmoke(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newPatchSubjectApi(),
			$this->createValidRequestData()
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	private function createPages(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->createPageWithSubjects(
			'PatchSubjectApiTest',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440000',
				label: new SubjectLabel( 'Test subject 426655440000' ),
			)
		);
	}

	private function newPatchSubjectApi(): PatchSubjectApi {
		$csrfValidatorstub = $this->createStub( CsrfValidator::class );
		$csrfValidatorstub->method( 'verifyCsrfToken' )->willReturn( true );

		return new PatchSubjectApi(
			csrfValidator: $csrfValidatorstub
		);
	}

	private function createValidRequestData(): RequestData {
		return new RequestData( [
			'method' => 'PATCH',
			'pathParams' => [
				'subjectId' => '123e4567-e89b-12d3-a456-426655440000'
			],
			'bodyContents' => <<<JSON
{
	"statements": {
		"Founded at": {
			"format": "number",
			"value": 2019
		},
		"Websites": {
			"format": "url",
			"value": [
				"https://professional.wiki",
				"https://wikibase.consulting"
			]
		},
		"Products": {
			"format": "relation",
			"value": [
				{
					"id": "00000000-1111-2222-1100-000000000004",
					"target": "12345678-0000-0000-0000-000000000004"
				},
				{
					"target": "12345678-0000-0000-0000-000000000005"
				}
			]
		},
		"DoNotWant": null
	}
}
JSON,
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}

	public function testPermissionDenied(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newPatchSubjectApi(),
			$this->createValidRequestData(),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'You do not have the necessary permissions to edit this subject', $responseData['message'] );
	}

}
