<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSubjectApi;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction
 * @group Database
 */
class DeleteSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	private const string SUBJECT_ID = 'sTestDSA1111111';

	public function testDeletesSubjectWithoutBody(): void {
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
		return $this->createRequestDataFor( self::SUBJECT_ID );
	}

	private function createRequestDataFor( string $subjectId, array $body = [] ): RequestData {
		return new RequestData( [
			'method' => 'DELETE',
			'pathParams' => [
				'subjectId' => $subjectId
			],
			'bodyContents' => json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}

	private function createPages(): void {
		$this->createPageWithSubjects(
			'DeleteSubjectApiTest',
			mainSubject: TestSubject::build(
				id: self::SUBJECT_ID,
				label: new SubjectLabel( 'Test subject ' . self::SUBJECT_ID ),
			)
		);
	}

	public function testDeleteWithComment(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newDeleteSubjectApi(),
			$this->createRequestDataFor( self::SUBJECT_ID, [ 'comment' => 'Test edit summary' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	/**
	 * A Subject the index does not resolve is answered as absent. Pinned at this layer because only
	 * the handler turns the exception into a status code.
	 */
	public function testUnresolvableSubjectIsNotFound(): void {
		$response = $this->executeHandler(
			$this->newDeleteSubjectApi(),
			$this->createValidRequestData()
		);

		$this->assertSame( 404, $response->getStatusCode() );
	}

	public function testReadableButNotEditablePageReturns403(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newDeleteSubjectApi(),
			$this->createValidRequestData(),
			authority: $this->authorityWithGlobalEditButNoPageEdit()
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'You do not have the necessary permissions to delete this subject', $responseData['message'] );
	}

	public function testMalformedSubjectIdIsRejected(): void {
		// SubjectId rejects the format with an InvalidArgumentException, which is a LogicException:
		// without its own catch arm it escapes run() and answers 500 rather than 400.
		$response = $this->executeHandler(
			$this->newDeleteSubjectApi(),
			$this->createRequestDataFor( 'notavalidid' )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testSubjectOnAnUnreadablePageAnswersLikeAnAbsentSubject(): void {
		$this->createPages();

		// One Authority for both requests: comparing responses obtained under two different
		// Authorities says nothing about what any single caller can tell apart.
		$authority = $this->authorityWithGlobalReadButNoPageRead();

		$unreadable = $this->executeHandler(
			$this->newDeleteSubjectApi(),
			$this->createValidRequestData(),
			authority: $authority
		);

		$absent = $this->executeHandler(
			$this->newDeleteSubjectApi(),
			$this->createRequestDataFor( 'sDoesNotExist99' ),
			authority: $authority
		);

		// A caller holding a harvested Subject id learns nothing about whether it exists.
		$this->assertSame( 404, $unreadable->getStatusCode() );
		$this->assertSame( 404, $absent->getStatusCode() );
		$this->assertSame(
			$this->bodyWithIdMasked( $absent, 'sDoesNotExist99' ),
			$this->bodyWithIdMasked( $unreadable, self::SUBJECT_ID )
		);
	}

	/**
	 * Masking the echoed id, which is only what the caller supplied, leaves everything that could
	 * tell the two answers apart.
	 */
	private function bodyWithIdMasked( ResponseInterface $response, string $subjectId ): array {
		return json_decode( str_replace( $subjectId, '<id>', $response->getBody()->getContents() ), true );
	}

}
