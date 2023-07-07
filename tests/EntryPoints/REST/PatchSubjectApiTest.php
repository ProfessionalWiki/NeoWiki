<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\PatchSubjectApi;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\PatchSubjectApi
 * @group database
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
			'bodyContents' => json_encode( [
				'properties' => [
					'animal' => 'bunny',
					'fluff' => 9001,
				]
			] ),
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
