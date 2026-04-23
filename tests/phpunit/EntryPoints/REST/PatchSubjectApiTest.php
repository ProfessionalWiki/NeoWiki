<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
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
				id: 'sTestSA11111111',
				label: new SubjectLabel( 'Test subject sTestSA11111111' ),
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
		return $this->createRequestData( $this->validBody() );
	}

	private function createRequestData( array $body ): RequestData {
		return new RequestData( [
			'method' => 'PATCH',
			'pathParams' => [
				'subjectId' => 'sTestSA11111111'
			],
			'bodyContents' => json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}

	private function validBody(): array {
		return [
			'label' => 'Test subject sTestSA11111111',
			'statements' => [
				'Founded at' => [
					'propertyType' => 'number',
					'value' => 2019
				],
				'Websites' => [
					'propertyType' => 'url',
					'value' => [
						'https://professional.wiki',
						'https://wikibase.consulting'
					]
				],
				'Products' => [
					'propertyType' => 'relation',
					'value' => [
						[
							'id' => 'rTestSA11111rr1',
							'target' => 'sTestSA11111114'
						],
						[
							'target' => 'sTestSA11111115'
						]
					]
				],
				'DoNotWant' => null
			]
		];
	}

	public function testPatchSubjectWithComment(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['comment'] = 'My edit summary';

		$response = $this->executeHandler(
			$this->newPatchSubjectApi(),
			$this->createRequestData( $body )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		// TODO: Should we verify the comment was saved in the revision?
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

	public function testLabelChange(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['label'] = 'Updated Test Subject sTestSA11111111'; // was 'Test subject sTestSA11111111'

		$response = $this->executeHandler(
			$this->newPatchSubjectApi(),
			$this->createRequestData( $body )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'Updated Test Subject sTestSA11111111', $responseData['label'] );
	}

	public function testRejectsBodyMissingStatements(): void {
		$body = $this->validBody();
		unset( $body['statements'] );

		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			$this->newPatchSubjectApi(),
			$this->createRequestData( $body )
		);
	}

}
