<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Title;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction
 * @group Database
 */
class CreateSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use MockAuthorityTrait;

	public function testCreatesSubject(): void {
		$this->createSchema( 'Employee' );

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createValidRequestData()
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( 'created', $responseData['status'] );

		$subject = NeoWikiExtension::getInstance()->newSubjectRepository()->getSubject( new SubjectId( $responseData['subjectId'] ) );

		$this->assertSame( 'Test subject', $subject->label->text );
		$this->assertSame( 'Employee', $subject->getSchemaId()->getText() );
		$this->assertEquals(
			new StatementList( [
				TestStatement::build( property: 'animal', value: 'bunny' ),
				TestStatement::build( property: 'fluff', value: new NumberValue( 9001 ), propertyType: 'number' ),
			] ),
			$subject->getStatements()
		);
	}

	private function newCreateSubjectApi( bool $isMainSubject = true ): CreateSubjectApi {
		$csrfValidatorstub = $this->createStub( CsrfValidator::class );
		$csrfValidatorstub->method( 'verifyCsrfToken' )->willReturn( true );

		return new CreateSubjectApi(
			isMainSubject: $isMainSubject,
			csrfValidator: $csrfValidatorstub
		);
	}

	private function createValidRequestData(): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [
				'pageId' => $this->getIdOfExistingPage()
			],
			'bodyContents' => json_encode( [
				'label' => 'Test subject',
				'schema' => 'Employee',
				'statements' => [
					'animal' => [
						'propertyType' => 'text',
						'value' => 'bunny'
					],
					'fluff' => [
						'propertyType' => 'number',
						'value' => 9001
					],
				]
			] ),
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}

	private function getIdOfExistingPage(): int {
		$title = Title::newFromText( 'CreateSubjectApiTest' );

		$this->editPage( $title, 'Whatever wikitext' );
		return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title )->getId();
	}

	public function testPermissionDenied(): void {
		$this->createSchema( 'Employee' );

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createValidRequestData(),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'You do not have the necessary permissions to create this subject', $responseData['message'] );
	}

}
