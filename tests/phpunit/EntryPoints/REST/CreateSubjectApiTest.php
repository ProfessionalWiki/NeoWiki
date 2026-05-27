<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

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
		$this->assertArrayHasKey( 'violations', $responseData );
		$this->assertSame( [], $responseData['violations'] );

		$subject = NeoWikiExtension::getInstance()->newSubjectRepository()->getSubject( new SubjectId( $responseData['subjectId'] ) );

		$this->assertSame( 'Test subject', $subject->label->text );
		$this->assertSame( 'Employee', $subject->getSchemaName()->getText() );
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
		return $this->createRequestData( $this->validBody() );
	}

	private function createRequestData( array $body ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [
				'pageId' => $this->getIdOfExistingPage()
			],
			'bodyContents' => json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}

	private function validBody(): array {
		return [
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
		];
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
		$this->assertArrayNotHasKey( 'violations', $responseData );
	}

	public function testResponseIncludesViolationsWhenRequiredPropertyMissing(): void {
		$this->createSchema(
			'CreateViolationSchema',
			'{"title":"CreateViolationSchema","propertyDefinitions":{"Status":{"type":"text","required":true}}}'
		);

		$body = [
			'label' => 'Subject with missing required',
			'schema' => 'CreateViolationSchema',
			'statements' => [],
		];

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createRequestData( $body )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( 'created', $responseData['status'] );
		$this->assertNotEmpty( $responseData['violations'] );
		$this->assertSame( 'required', $responseData['violations'][0]['code'] );
		$this->assertSame( 'Status', $responseData['violations'][0]['propertyName'] );
	}

	public function testAlreadyExistingMainSubjectResponseOmitsViolations(): void {
		$this->createSchema( 'Employee' );

		$pageTitle = 'CreateSubjectApiTestAlreadyExists';
		$this->createPageWithSubjects(
			$pageTitle,
			mainSubject: TestSubject::build(
				id: 'sTestCSA11111A1',
				label: new SubjectLabel( 'First main subject' ),
			)
		);
		$pageId = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $pageTitle ) )->getId();

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			new RequestData( [
				'method' => 'POST',
				'pathParams' => [ 'pageId' => $pageId ],
				'bodyContents' => json_encode( $this->validBody() ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'Subject already exists', $responseData['message'] );
		$this->assertArrayNotHasKey( 'violations', $responseData );
	}

	public function testRejectsBodyMissingLabel(): void {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$body = $this->validBody();
		unset( $body['label'] );

		$this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createRequestData( $body )
		);
	}

	public function testEmptyLabelReturns400(): void {
		$this->createSchema( 'Employee' );

		$body = $this->validBody();
		$body['label'] = '   ';

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createRequestData( $body )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testEnforcementBlockedReturns422(): void {
		$this->setMwGlobals( 'wgNeoWikiValidationEnforced', true );

		$this->createSchema(
			'CreateEnforcementSchema',
			'{"title":"CreateEnforcementSchema","propertyDefinitions":{"Required":{"type":"text","required":true}}}'
		);

		$body = [
			'label' => 'Subject with missing required',
			'schema' => 'CreateEnforcementSchema',
			'statements' => [],
		];

		$response = $this->executeHandler(
			$this->newCreateSubjectApi(),
			$this->createRequestData( $body )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 422, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'Validation failed', $responseData['message'] );
		$this->assertNotEmpty( $responseData['violations'] );
		$this->assertSame( 'Required', $responseData['violations'][0]['propertyName'] );
		$this->assertSame( 'required', $responseData['violations'][0]['code'] );
	}

}
