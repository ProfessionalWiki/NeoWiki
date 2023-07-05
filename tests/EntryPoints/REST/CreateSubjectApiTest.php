<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Title;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction
 * @group database
 */
class CreateSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testCreatesSubject(): void {
		$this->createSchema( 'Employee' );

		$csrfValidatorstub = $this->createStub( CsrfValidator::class );
		$csrfValidatorstub->method( 'verifyCsrfToken' )->willReturn( true );

		$response = $this->executeHandler(
			new CreateSubjectApi(
				isMainSubject: true,
				csrfValidator: $csrfValidatorstub
			),
			new RequestData( [
				'method' => 'POST',
				'pathParams' => [
					'pageId' => $this->getIdOfExistingPage()
				],
				'bodyContents' => json_encode( [
					'label' => 'Test subject',
					'schema' => 'Employee',
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

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( 'created', $responseData['status'] );

		$subject = NeoWikiExtension::getInstance()->newSubjectRepository()->getSubject( new SubjectId( $responseData['subjectId'] ) );

		$this->assertSame( 'Test subject', $subject->label->text );
		$this->assertSame( 'Employee', $subject->getSchemaId()->getText() );
		$this->assertSame( [ 'animal' => 'bunny', 'fluff' => 9001 ], $subject->getStatements()->asMap() );
	}

	private function getIdOfExistingPage(): int {
		$title = Title::newFromText( 'CreateSubjectApiTest' );

		$this->editPage( $title, 'Whatever wikitext' );
		return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title )->getId();
	}

}
