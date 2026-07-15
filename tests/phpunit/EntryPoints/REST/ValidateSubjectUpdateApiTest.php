<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ValidateSubjectUpdateApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ValidateSubjectUpdateApi
 * @group Database
 */
class ValidateSubjectUpdateApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;

	public function testHappyPathReturns200WithEmptyViolations(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newValidateSubjectUpdateApi(),
			$this->createRequestData( 'sTestSU11111111', $this->validBody() )
		);

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [], $body['violations'] );
	}

	public function testEmptyLabelReturnsLabelRequiredViolation(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['label'] = '';

		$response = $this->executeHandler(
			$this->newValidateSubjectUpdateApi(),
			$this->createRequestData( 'sTestSU11111111', $body )
		);

		$responseBody = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertCount( 1, $responseBody['violations'] );
		$this->assertSame( 'label-required', $responseBody['violations'][0]['code'] );
		$this->assertNull( $responseBody['violations'][0]['propertyName'] );
	}

	public function testInvalidConstraintReturnsViolation(): void {
		$this->createSchema(
			TestSubject::DEFAULT_SCHEMA_ID,
			'{"title":"' . TestSubject::DEFAULT_SCHEMA_ID . '","propertyDefinitions":{"Founded at":{"type":"number","maximum":2000}}}'
		);
		$this->createPageWithSubjects(
			'ValidateSubjectUpdateApiConstraintTest',
			mainSubject: TestSubject::build(
				id: 'sTestSU11111111',
				label: new SubjectLabel( 'Test subject sTestSU11111111' ),
			)
		);

		$body = $this->validBody();
		$body['statements'] = [
			'Founded at' => [ 'propertyType' => 'number', 'value' => 2025 ],
		];

		$response = $this->executeHandler(
			$this->newValidateSubjectUpdateApi(),
			$this->createRequestData( 'sTestSU11111111', $body )
		);

		$responseBody = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertCount( 1, $responseBody['violations'] );
		$this->assertSame( 'max-value', $responseBody['violations'][0]['code'] );
	}

	public function testRelationTargetWithUnresolvableSourceProducesViolation(): void {
		$this->createSchema(
			TestSubject::DEFAULT_SCHEMA_ID,
			'{"title":"' . TestSubject::DEFAULT_SCHEMA_ID . '","propertyDefinitions":'
				. '{"Owner":{"type":"relation","relation":"has","targetSchema":"Company","multiple":false}}}'
		);
		$this->createPageWithSubjects(
			'ValidateSubjectUpdateApiUnresolvableSourceTest',
			mainSubject: TestSubject::build(
				id: 'sTestSU11111111',
				label: new SubjectLabel( 'Test subject sTestSU11111111' ),
			)
		);

		$body = $this->validBody();
		$body['statements'] = [
			'Owner' => [
				'propertyType' => 'relation',
				'value' => [
					[ 'id' => 'r1demo5rrrrrrr1', 'target' => 'ghostwiki:s1demo4sssssss1' ],
				],
			],
		];

		$response = $this->executeHandler(
			$this->newValidateSubjectUpdateApi(),
			$this->createRequestData( 'sTestSU11111111', $body )
		);

		$responseBody = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertCount( 1, $responseBody['violations'] );
		$this->assertSame( 'relation-target-source-unresolvable', $responseBody['violations'][0]['code'] );
		$this->assertSame( 'Owner', $responseBody['violations'][0]['propertyName'] );
		$this->assertSame( [ 'ghostwiki' ], $responseBody['violations'][0]['args'] );
	}

	public function testNonExistentSubjectReturns404(): void {
		$response = $this->executeHandler(
			$this->newValidateSubjectUpdateApi(),
			$this->createRequestData( 'sDoesNotExist99', $this->validBody() )
		);

		$responseBody = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertSame( 'error', $responseBody['status'] );
		$this->assertStringContainsString( 'not found', $responseBody['message'] );
	}

	public function testMalformedSubjectIdReturns400(): void {
		$response = $this->executeHandler(
			$this->newValidateSubjectUpdateApi(),
			$this->createRequestData( 'not-valid', $this->validBody() )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testCommentFieldIsAccepted(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['comment'] = 'My validation comment';

		$response = $this->executeHandler(
			$this->newValidateSubjectUpdateApi(),
			$this->createRequestData( 'sTestSU11111111', $body )
		);

		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testNoWriteOccurs(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['label'] = 'Changed Label That Should Not Persist';

		$this->executeHandler(
			$this->newValidateSubjectUpdateApi(),
			$this->createRequestData( 'sTestSU11111111', $body )
		);

		$subject = $this->getSubjectFromRepository( 'sTestSU11111111' );
		$this->assertSame( 'Test subject sTestSU11111111', $subject->getLabel()->text );
	}

	public function testSubjectWithMissingSchemaReturnsSchemaNotFoundViolation(): void {
		$this->markTestSkipped(
			'The schema-not-found path requires a Subject to be findable via the Neo4j page-identifier '
			. 'lookup AND for its Schema to be absent. In practice, a Subject created with a never-existing '
			. 'Schema cannot be projected into Neo4j, so the lookup returns null and the handler responds 404 '
			. 'before reaching the schema check. The defensive schema-not-found path remains in the code as '
			. 'belt-and-suspenders for the rare scenario where a Schema page is deleted after Subject creation; '
			. 'reaching it in a test requires a fixture sequence (create schema -> create subject -> rebuild '
			. 'projection -> delete schema page) that this test harness does not currently support.'
		);
	}

	public function testNeedsWriteAccessReturnsFalse(): void {
		$this->assertFalse( $this->newValidateSubjectUpdateApi()->needsWriteAccess() );
	}

	private function createPages(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->createPageWithSubjects(
			'ValidateSubjectUpdateApiTest',
			mainSubject: TestSubject::build(
				id: 'sTestSU11111111',
				label: new SubjectLabel( 'Test subject sTestSU11111111' ),
			)
		);
	}

	private function newValidateSubjectUpdateApi(): ValidateSubjectUpdateApi {
		return new ValidateSubjectUpdateApi(
			query: NeoWikiExtension::getInstance()->newValidateSubjectUpdateQuery(),
		);
	}

	private function createRequestData( string $subjectId, array $body ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [ 'subjectId' => $subjectId ],
			'bodyContents' => json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
		] );
	}

	private function validBody(): array {
		return [
			'label' => 'Test subject sTestSU11111111',
			'statements' => [],
		];
	}

	private function getSubjectFromRepository( string $subjectId ): Subject {
		$subject = NeoWikiExtension::getInstance()
			->newSubjectRepository()
			->getSubject( new SubjectId( $subjectId ) );

		$this->assertNotNull( $subject );
		return $subject;
	}

}
