<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ReplaceSubjectApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ReplaceSubjectApi
 * @group Database
 */
class ReplaceSubjectApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;

	public function testHappyPathReturns200WithUpdatedStatus(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createValidRequestData()
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'updated', $responseData['status'] );
		$this->assertSame( 'sTestSA11111111', $responseData['subjectId'] );
	}

	public function testOmittedStatementKeyIsDeleted(): void {
		$this->createPages();

		$bodyWithTwoStatements = $this->validBody();
		$bodyWithTwoStatements['statements'] = [
			'Founded at' => [ 'propertyType' => 'number', 'value' => 2019 ],
			'Website' => [ 'propertyType' => 'url', 'value' => [ 'https://example.com' ] ],
		];
		$this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createRequestData( $bodyWithTwoStatements )
		);

		$bodyOmittingOne = $this->validBody();
		$bodyOmittingOne['statements'] = [
			'Founded at' => [ 'propertyType' => 'number', 'value' => 2019 ],
		];
		$this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createRequestData( $bodyOmittingOne )
		);

		$subject = $this->getSubjectFromRepository( 'sTestSA11111111' );
		$this->assertSame( [ 'Founded at' ], array_keys( $subject->getStatements()->asArray() ) );
	}

	public function testEmptyStatementsMapClearsAll(): void {
		$this->createPages();

		$this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createValidRequestData()
		);

		$bodyEmpty = $this->validBody();
		$bodyEmpty['statements'] = [];
		$this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createRequestData( $bodyEmpty )
		);

		$subject = $this->getSubjectFromRepository( 'sTestSA11111111' );
		$this->assertSame( [], $subject->getStatements()->asArray() );
	}

	public function testNonExistentSubjectReturns404(): void {
		$body = $this->validBody();

		$response = $this->executeHandler(
			$this->newReplaceSubjectApi(),
			new RequestData( [
				'method' => 'PUT',
				'pathParams' => [ 'subjectId' => 'sDoesNotExist99' ],
				'bodyContents' => json_encode( $body ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertStringContainsString( 'not found', $responseData['message'] );
	}

	public function testMissingLabelReturns400(): void {
		$body = $this->validBody();
		unset( $body['label'] );

		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createRequestData( $body )
		);
	}

	public function testEmptyLabelReturns400(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['label'] = '   ';

		$response = $this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createRequestData( $body )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testMissingStatementsReturns400(): void {
		$body = $this->validBody();
		unset( $body['statements'] );

		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createRequestData( $body )
		);
	}

	public function testPermissionDeniedReturns403(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createValidRequestData(),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
	}

	public function testCommentIsAccepted(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['comment'] = 'My edit summary';

		$response = $this->executeHandler(
			$this->newReplaceSubjectApi(),
			$this->createRequestData( $body )
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	private function createPages(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->createPageWithSubjects(
			'ReplaceSubjectApiTest',
			mainSubject: TestSubject::build(
				id: 'sTestSA11111111',
				label: new SubjectLabel( 'Test subject sTestSA11111111' ),
			)
		);
	}

	private function newReplaceSubjectApi(): ReplaceSubjectApi {
		$csrfValidatorStub = $this->createStub( CsrfValidator::class );
		$csrfValidatorStub->method( 'verifyCsrfToken' )->willReturn( true );

		return new ReplaceSubjectApi(
			csrfValidator: $csrfValidatorStub
		);
	}

	private function createValidRequestData(): RequestData {
		return $this->createRequestData( $this->validBody() );
	}

	private function createRequestData( array $body ): RequestData {
		return new RequestData( [
			'method' => 'PUT',
			'pathParams' => [
				'subjectId' => 'sTestSA11111111',
			],
			'bodyContents' => json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		] );
	}

	private function validBody(): array {
		return [
			'label' => 'Test subject sTestSA11111111',
			'statements' => [
				'Founded at' => [
					'propertyType' => 'number',
					'value' => 2019,
				],
			],
		];
	}

	public function testEnforcementBlockedReturns422(): void {
		$this->setMwGlobals( 'wgNeoWikiValidationEnforced', true );

		$this->createSchema(
			'EnforcementSchema',
			'{"title":"EnforcementSchema","propertyDefinitions":{"Required":{"type":"text","required":true}}}'
		);
		$this->createPageWithSubjects(
			'ReplaceSubjectApiEnforcementTest',
			mainSubject: TestSubject::build(
				id: 'sTestSA11111144',
				label: new SubjectLabel( 'Was clean' ),
				schemaName: new SchemaName( 'EnforcementSchema' ),
				statements: new StatementList( [
					TestStatement::build( property: 'Required', value: 'present' ),
				] )
			)
		);

		$response = $this->executeHandler(
			$this->newReplaceSubjectApi(),
			new RequestData( [
				'method' => 'PUT',
				'pathParams' => [ 'subjectId' => 'sTestSA11111144' ],
				'bodyContents' => json_encode( [
					'label' => 'After',
					'statements' => [],
				] ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 422, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertSame( 'Validation failed', $responseData['message'] );
		$this->assertNotEmpty( $responseData['violations'] );
		$this->assertSame( 'Required', $responseData['violations'][0]['propertyName'] );
		$this->assertSame( 'required', $responseData['violations'][0]['code'] );
	}

	private function getSubjectFromRepository( string $subjectId ): Subject {
		$subject = NeoWikiExtension::getInstance()
			->newSubjectRepository()
			->getSubject( new SubjectId( $subjectId ) );

		$this->assertNotNull( $subject );
		return $subject;
	}

}
