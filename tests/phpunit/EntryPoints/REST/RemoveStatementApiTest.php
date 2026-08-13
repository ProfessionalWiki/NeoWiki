<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Title\Title;
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
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\RemoveStatementApi
 * @group Database
 */
class RemoveStatementApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

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

	public function testReadableButNotEditablePageReturns403(): void {
		$this->createSubjectPage();

		// The caller can read the page - so its existence is already public - but cannot edit it.
		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website' ),
			authority: $this->authorityWithGlobalEditButNoPageEdit()
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
	}

	public function testSubjectOnAnUnreadablePageAnswersLikeAnAbsentSubject(): void {
		$this->createSubjectPage();

		// One Authority for both requests: comparing responses obtained under two different
		// Authorities says nothing about what any single caller can tell apart.
		$authority = $this->authorityWithGlobalReadButNoPageRead();

		$unreadable = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website' ),
			authority: $authority
		);

		$absent = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website', subjectId: 'sDoesNotExist99' ),
			authority: $authority
		);

		$unreadableData = json_decode( $unreadable->getBody()->getContents(), true );
		$absentData = json_decode( $absent->getBody()->getContents(), true );

		// A caller holding a harvested Subject id learns nothing about whether it exists. The
		// message names the id the caller supplied, so it carries nothing they did not send.
		$this->assertSame( 404, $unreadable->getStatusCode() );
		$this->assertSame( $unreadable->getStatusCode(), $absent->getStatusCode() );
		$this->assertSame( $unreadableData['status'], $absentData['status'] );
	}

	public function testCommentIsAccepted(): void {
		$this->createSubjectPage();

		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website', [ 'comment' => 'My edit summary' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testCommentBecomesTheEditSummary(): void {
		$this->createSubjectPage();

		$this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website', [ 'comment' => 'My edit summary' ] )
		);

		$this->assertSame(
			'My edit summary',
			$this->latestRevisionOfSubjectPage()->getComment()?->text
		);
	}

	private function latestRevisionOfSubjectPage(): RevisionRecord {
		$revision = $this->getServiceContainer()->getRevisionLookup()->getRevisionByTitle(
			Title::newFromText( 'RemoveStatementApiTest' )
		);

		$this->assertNotNull( $revision );
		return $revision;
	}

	public function testUpdatedResponseOmitsTheSchemaWhenTheSchemaIsMissing(): void {
		$this->createSubjectPage();
		$this->deletePage(
			$this->getServiceContainer()->getWikiPageFactory()->newFromTitle(
				Title::newFromText( 'RemoveStatementSchema', NeoWikiExtension::NS_SCHEMA )
			)
		);

		$this->startFreshRequest();

		$response = $this->executeHandler(
			$this->newRemoveStatementApi(),
			$this->newRequest( 'Website' )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertArrayHasKey( 'subject', $responseData );
		$this->assertArrayNotHasKey( 'schema', $responseData );
	}

	public function testEnforcementBlocksRemovingARequiredStatement(): void {
		$this->setMwGlobals( 'wgNeoWikiEnforceValidation', true );
		$this->createSubjectPage(
			'{"title":"RemoveStatementSchema","propertyDefinitions":'
				. '{"Website":{"type":"url","required":{"severity":"error"}}}}'
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
