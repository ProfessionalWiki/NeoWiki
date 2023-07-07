<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSubjectApi;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction
 * @group database
 */
class DeleteSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use MockAuthorityTrait;

	public function testSmoke(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newDeleteSubjectApi(),
			$this->createValidRequestData()
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	private function newDeleteSubjectApi(): DeleteSubjectApi {
		$csrfValidatorstub = $this->createStub( CsrfValidator::class );
		$csrfValidatorstub->method( 'verifyCsrfToken' )->willReturn( true );

		return new DeleteSubjectApi(
			csrfValidator: $csrfValidatorstub
		);
	}

	private function createValidRequestData(): RequestData {
		return new RequestData( [
			'method' => 'DELETE',
			'pathParams' => [
				'subjectId' => '123e4567-e89b-12d3-a456-426655440000'
			],
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}

	private function createPages(): void {
		$this->createPageWithSubjects(
			'DeleteSubjectApiTest',
			mainSubject: TestSubject::build(
				id: '123e4567-e89b-12d3-a456-426655440000',
				label: new SubjectLabel( 'Test subject 426655440000' ),
			)
		);
	}

	public function testPermissionDenied(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newDeleteSubjectApi(),
			$this->createValidRequestData(),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'You do not have the necessary permissions to delete this subject', $responseData['message'] );
	}

}
