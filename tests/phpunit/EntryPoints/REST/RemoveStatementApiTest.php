<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\RemoveStatementApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\RemoveStatementApi
 * @group Database
 */
class RemoveStatementApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;

	private const string SUBJECT_ID = 'sTestRS11111111';

	private function createSubjectPage( ?string $schemaJson = null ): void {
		$this->createSchema(
			'RemoveStatementSchema',
			$schemaJson ?? '{"title":"RemoveStatementSchema","propertyDefinitions":{"Website":{"type":"url"}}}'
		);
		$this->createPageWithSubjects(
			'RemoveStatementApiTest',
			mainSubject: TestSubject::build(
				id: self::SUBJECT_ID,
				label: new SubjectLabel( 'Professional Wiki' ),
				schemaName: new SchemaName( 'RemoveStatementSchema' ),
				statements: new StatementList( [
					TestStatement::build( property: 'Website', value: 'https://pro.wiki', propertyType: 'url' ),
					TestStatement::build( property: 'Founded at', value: 'kept' ),
				] ),
			)
		);
	}

	private function newRemoveStatementApi(): RemoveStatementApi {
		$csrfValidatorStub = $this->createStub( CsrfValidator::class );
		$csrfValidatorStub->method( 'verifyCsrfToken' )->willReturn( true );

		return new RemoveStatementApi( csrfValidator: $csrfValidatorStub );
	}

	private function newRequest(
		string $propertyName,
		array $body = [],
		string $subjectId = self::SUBJECT_ID
	): RequestData {
		return new RequestData( [
			'method' => 'DELETE',
			'pathParams' => [
				'subjectId' => $subjectId,
				'propertyName' => $propertyName,
			],
			'bodyContents' => json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
		] );
	}

	private function getSubjectFromRepository(): Subject {
		$subject = NeoWikiExtension::getInstance()
			->newSubjectRepository()
			->getSubject( new SubjectId( self::SUBJECT_ID ) );

		$this->assertNotNull( $subject );
		return $subject;
	}

	/**
	 * @return list<string> The property names of the Subject's Statements.
	 */
	private function getStoredPropertyNames(): array {
		return array_keys( $this->getSubjectFromRepository()->getStatements()->asArray() );
	}

	public function testHappyPathReturns200WithUpdatedStatus(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website' )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'updated', $responseData['status'] );
		$this->assertSame( self::SUBJECT_ID, $responseData['subjectId'] );
	}

	public function testOnlyTheNamedStatementIsRemoved(): void {
		$this->createSubjectPage();

		$this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website' )
		);

		$this->assertSame( [ 'Founded at' ], $this->getStoredPropertyNames() );
	}

	public function testRemovingAnAbsentStatementLeavesTheSubjectUnchanged(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Never set' )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [ 'Website', 'Founded at' ], $this->getStoredPropertyNames() );
	}

	public function testNonExistentSubjectReturns404(): void {
		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website', subjectId: 'sDoesNotExist99' )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
	}

	public function testMalformedSubjectIdReturns400(): void {
		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website', subjectId: 'not-a-subject-id' )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testPermissionDeniedReturns403(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website' ),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
	}

	public function testCommentIsAccepted(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website', [ 'comment' => 'My edit summary' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testEnforcementBlocksRemovingARequiredStatement(): void {
		$this->setMwGlobals( 'wgNeoWikiEnforceValidation', true );
		$this->createSubjectPage(
			'{"title":"RemoveStatementSchema","propertyDefinitions":{"Website":{"type":"url","required":true}}}'
		);

		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website' )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 422, $response->getStatusCode() );
		$this->assertSame( 'Validation failed', $responseData['message'] );
		$this->assertSame( [ 'Website', 'Founded at' ], $this->getStoredPropertyNames() );
	}

}
