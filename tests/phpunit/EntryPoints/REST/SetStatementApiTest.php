<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SetStatementApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SetStatementApi
 * @group Database
 */
class SetStatementApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;

	private const string SUBJECT_ID = 'sTestSS11111111';

	private function createSubjectPage( ?StatementList $statements = null ): void {
		$this->createSchema(
			'SetStatementSchema',
			'{"title":"SetStatementSchema","propertyDefinitions":{"Website":{"type":"url"},"Founded at":{"type":"number"}}}'
		);
		$this->createPageWithSubjects(
			'SetStatementApiTest',
			mainSubject: TestSubject::build(
				id: self::SUBJECT_ID,
				label: new SubjectLabel( 'Professional Wiki' ),
				schemaName: new SchemaName( 'SetStatementSchema' ),
				statements: $statements,
			)
		);
	}

	private function newSetStatementApi(): SetStatementApi {
		$csrfValidatorStub = $this->createStub( CsrfValidator::class );
		$csrfValidatorStub->method( 'verifyCsrfToken' )->willReturn( true );

		return new SetStatementApi( csrfValidator: $csrfValidatorStub );
	}

	private function newRequest( string $propertyName, array $body, string $subjectId = self::SUBJECT_ID ): RequestData {
		return new RequestData( [
			'method' => 'PUT',
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
	 * @return mixed The stored value, or null when the Subject has no Statement for the property.
	 */
	private function getStoredValue( string $propertyName ): mixed {
		return $this->getSubjectFromRepository()
			->getStatements()
			->getStatement( new PropertyName( $propertyName ) )
			?->getValue()
			->toScalars();
	}

	public function testHappyPathReturns200WithUpdatedStatus(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'statement' => [ 'propertyType' => 'url', 'value' => [ 'https://pro.wiki' ] ] ] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'updated', $responseData['status'] );
		$this->assertSame( self::SUBJECT_ID, $responseData['subjectId'] );
		$this->assertSame( [], $responseData['violations'] );
	}

	public function testStatementIsStored(): void {
		$this->createSubjectPage();

		$this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'statement' => [ 'propertyType' => 'url', 'value' => [ 'https://pro.wiki' ] ] ] )
		);

		$this->assertSame( [ 'https://pro.wiki' ], $this->getStoredValue( 'Website' ) );
	}

	public function testMultiWordPropertyNameIsStoredVerbatim(): void {
		$this->createSubjectPage();

		$this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Founded at', [ 'statement' => [ 'propertyType' => 'number', 'value' => 2019 ] ] )
		);

		$this->assertSame( 2019, $this->getStoredValue( 'Founded at' ) );
	}

	public function testOtherStatementsAndLabelAreLeftAlone(): void {
		$this->createSubjectPage( new StatementList( [
			TestStatement::build( property: 'Founded at', value: 'untouched' ),
		] ) );

		$this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'statement' => [ 'propertyType' => 'url', 'value' => [ 'https://pro.wiki' ] ] ] )
		);

		$this->assertSame( [ 'untouched' ], $this->getStoredValue( 'Founded at' ) );
		$this->assertSame( 'Professional Wiki', $this->getSubjectFromRepository()->getLabel()->text );
	}

	public function testPropertyTypeFallsBackToTheSchemaType(): void {
		$this->createSubjectPage();

		$this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'statement' => [ 'value' => [ 'https://pro.wiki' ] ] ] )
		);

		$this->assertSame(
			'url',
			$this->getSubjectFromRepository()
				->getStatements()
				->getStatement( new PropertyName( 'Website' ) )
				->getPropertyType()
		);
	}

	public function testGivenPropertyTypeIsStoredOverTheSchemaType(): void {
		$this->createSubjectPage();

		$this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'statement' => [ 'propertyType' => 'text', 'value' => [ 'not a url' ] ] ] )
		);

		$this->assertSame(
			'text',
			$this->getSubjectFromRepository()
				->getStatements()
				->getStatement( new PropertyName( 'Website' ) )
				->getPropertyType()
		);
	}

	public function testOmittedPropertyTypeForUnknownPropertyReturns400(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Not in the Schema', [ 'statement' => [ 'value' => [ 'a value' ] ] ] )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testEmptyValueRemovesTheStatement(): void {
		$this->createSubjectPage( new StatementList( [
			TestStatement::build( property: 'Website', value: 'https://pro.wiki', propertyType: 'url' ),
		] ) );

		$this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'statement' => [ 'propertyType' => 'url', 'value' => [] ] ] )
		);

		$this->assertNull( $this->getStoredValue( 'Website' ) );
	}

	public function testValueNotFittingItsPropertyTypeReturns400(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Founded at', [ 'statement' => [ 'propertyType' => 'number', 'value' => 'not a number' ] ] )
		);

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertNull( $this->getStoredValue( 'Founded at' ) );
	}

	public function testStatementWithoutAValueReturns400(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Founded at', [ 'statement' => [ 'propertyType' => 'number' ] ] )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testMissingStatementReturns400(): void {
		$this->createSubjectPage();

		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'comment' => 'No statement here' ] )
		);
	}

	public function testMalformedSubjectIdReturns400(): void {
		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest(
				'Website',
				[ 'statement' => [ 'propertyType' => 'url', 'value' => [ 'https://pro.wiki' ] ] ],
				subjectId: 'not-a-subject-id'
			)
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testNonExistentSubjectReturns404(): void {
		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest(
				'Website',
				[ 'statement' => [ 'propertyType' => 'url', 'value' => [ 'https://pro.wiki' ] ] ],
				subjectId: 'sDoesNotExist99'
			)
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
	}

	public function testPermissionDeniedReturns403(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'statement' => [ 'propertyType' => 'url', 'value' => [ 'https://pro.wiki' ] ] ] ),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
	}

	public function testCommentIsAccepted(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [
				'statement' => [ 'propertyType' => 'url', 'value' => [ 'https://pro.wiki' ] ],
				'comment' => 'My edit summary',
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testResponseIncludesViolationsOfUntouchedProperties(): void {
		$this->createSchema(
			'SetStatementViolationSchema',
			'{"title":"SetStatementViolationSchema","propertyDefinitions":{"Website":{"type":"url"},"Status":{"type":"text","required":true}}}'
		);
		$this->createPageWithSubjects(
			'SetStatementApiViolationTest',
			mainSubject: TestSubject::build(
				id: self::SUBJECT_ID,
				label: new SubjectLabel( 'Missing Status' ),
				schemaName: new SchemaName( 'SetStatementViolationSchema' ),
			)
		);

		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'statement' => [ 'propertyType' => 'url', 'value' => [ 'https://pro.wiki' ] ] ] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'required', $responseData['violations'][0]['code'] );
		$this->assertSame( 'Status', $responseData['violations'][0]['propertyName'] );
	}

	public function testEnforcementBlockedReturns422(): void {
		$this->setMwGlobals( 'wgNeoWikiEnforceValidation', true );
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newSetStatementApi(),
			$this->newRequest( 'Website', [ 'statement' => [ 'propertyType' => 'url', 'value' => [ 'not-a-url' ] ] ] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 422, $response->getStatusCode() );
		$this->assertSame( 'Validation failed', $responseData['message'] );
		$this->assertNull( $this->getStoredValue( 'Website' ) );
	}

}
