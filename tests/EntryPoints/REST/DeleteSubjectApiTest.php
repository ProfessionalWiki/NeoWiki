<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
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

	public function testSmoke(): void {
		$this->createPages();

		$csrfValidatorstub = $this->createStub( CsrfValidator::class );
		$csrfValidatorstub->method( 'verifyCsrfToken' )->willReturn( true );

		$response = $this->executeHandler(
			new DeleteSubjectApi(
				csrfValidator: $csrfValidatorstub
			),
			new RequestData( [
				'method' => 'DELETE',
				'pathParams' => [
					'subjectId' => '123e4567-e89b-12d3-a456-426655440000'
				],
				'headers' => [
					'Content-Type' => 'application/json'
				]
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
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

}
