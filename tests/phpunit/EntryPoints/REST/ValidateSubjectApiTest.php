<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ValidateSubjectApi;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ValidateSubjectApi
 * @group Database
 */
class ValidateSubjectApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;

	private const string TARGET_ID = 'srt111111111aaa';
	private const string TARGET_SCHEMA = 'Whereabouts';
	private const string RESTRICTED_PAGE = 'RestrictedPage';

	public function testHappyPathReturns200WithEmptyViolations(): void {
		$this->createPages();

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $this->validBody() )
		);

		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [], $body['violations'] );
	}

	public function testEmptyLabelProducesNoViolation(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['label'] = '';

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $body )
		);

		$responseBody = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [], $responseBody['violations'] );
	}

	public function testOmittedLabelProducesNoViolation(): void {
		$this->createPages();

		$body = $this->validBody();
		unset( $body['label'] );

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $body )
		);

		$responseBody = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [], $responseBody['violations'] );
	}

	public function testNonExistentSchemaReturns404(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['schema'] = 'NoSuchSchema';

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $body )
		);

		$this->assertSame( 404, $response->getStatusCode() );
	}

	public function testEmptySchemaNameReturns400(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['schema'] = '';

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $body )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testValuePartIndexOmittedWhenNull(): void {
		$this->createSchemaWithRequiredProperty();

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $this->validBody() )
		);

		$responseBody = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertCount( 1, $responseBody['violations'] );
		$this->assertArrayNotHasKey( 'valuePartIndex', $responseBody['violations'][0] );
	}

	public function testNeedsWriteAccessReturnsFalse(): void {
		$this->assertFalse( $this->newValidateSubjectApi()->needsWriteAccess() );
	}

	public function testInvalidSelectValueProducesInvalidOptionViolation(): void {
		$this->createSchemaWithSelectProperty();

		$body = $this->validBody();
		$body['statements'] = [
			'Status' => [ 'propertyType' => 'select', 'value' => [ 'bogus-id' ] ],
		];

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $body )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$responseBody = json_decode( $response->getBody()->getContents(), true );
		$codes = array_column( $responseBody['violations'], 'code' );
		$this->assertContains( 'invalid-option', $codes );
	}

	public function testValidSelectLabelIsResolvedAndValidates(): void {
		$this->createSchemaWithSelectProperty();

		$body = $this->validBody();
		$body['statements'] = [
			'Status' => [ 'propertyType' => 'select', 'value' => [ 'Active' ] ],
		];

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $body )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$responseBody = json_decode( $response->getBody()->getContents(), true );
		$this->assertSame( [], $responseBody['violations'] );
	}

	/**
	 * A relation target the caller may not read must be indistinguishable from one that was never
	 * minted: reporting the schema mismatch tells the caller the Subject exists and which Schema it
	 * uses, on a page they cannot open (#1266).
	 */
	public function testTargetOnAnUnreadablePageIsReportedAsNotFoundRatherThanASchemaMismatch(): void {
		$this->createSchemaWithRelationProperty();
		$this->createPageWithMismatchingTarget( self::RESTRICTED_PAGE );

		$violations = $this->validateRelationToTheTarget();

		$this->assertSame( [ 'relation-target-not-found' ], array_column( $violations, 'code' ) );
		$this->assertStringNotContainsString( self::TARGET_SCHEMA, json_encode( $violations ) );
	}

	/**
	 * The counterpart of the test above, differing only in which page hosts the target: the check
	 * itself still runs, so hiding the mismatch is the read gate rather than a disabled check.
	 */
	public function testTargetOnAReadablePageStillReportsItsSchemaMismatch(): void {
		$this->createSchemaWithRelationProperty();
		$this->createPageWithMismatchingTarget( 'ReadablePage' );

		$violations = $this->validateRelationToTheTarget();

		$this->assertSame( [ 'relation-target-schema-mismatch' ], array_column( $violations, 'code' ) );
		$this->assertSame( [ 'Person', self::TARGET_SCHEMA ], $violations[0]['args'] );
	}

	private function createPageWithMismatchingTarget( string $pageName ): void {
		$this->createPageWithSubjects(
			$pageName,
			TestSubject::build( id: self::TARGET_ID, schemaName: new SchemaName( self::TARGET_SCHEMA ) )
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function validateRelationToTheTarget(): array {
		$body = $this->validBody();
		$body['statements'] = [
			'Owner' => [ 'propertyType' => 'relation', 'value' => [ [ 'target' => self::TARGET_ID ] ] ],
		];

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $body ),
			[],
			[],
			[],
			[],
			$this->newUserWhoCannotRead( self::RESTRICTED_PAGE )
		);

		$this->assertSame( 200, $response->getStatusCode() );

		return json_decode( $response->getBody()->getContents(), true )['violations'];
	}

	private function newUserWhoCannotRead( string $pageName ): Authority {
		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function ( $title, $user, $action, &$result ) use ( $pageName ): bool {
				if ( $action === 'read' && $title->getPrefixedText() === $pageName ) {
					$result = [ 'badaccess-group0' ];
					return false;
				}
				return true;
			}
		);

		return $this->getTestUser()->getAuthority();
	}

	private function createSchemaWithRelationProperty(): void {
		$this->createSchema(
			TestSubject::DEFAULT_SCHEMA_ID,
			json_encode( [
				'title' => TestSubject::DEFAULT_SCHEMA_ID,
				'propertyDefinitions' => [
					'Owner' => [ 'type' => 'relation', 'relation' => 'ownedBy', 'targetSchema' => 'Person' ],
				],
			] )
		);
	}

	private function createPages(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	private function createSchemaWithRequiredProperty(): void {
		$this->createSchema(
			TestSubject::DEFAULT_SCHEMA_ID,
			'{"title":"' . TestSubject::DEFAULT_SCHEMA_ID . '","propertyDefinitions":{"Status":{"type":"text","required":true}}}'
		);
	}

	private function createSchemaWithSelectProperty(): void {
		$json = json_encode( [
			'title' => TestSubject::DEFAULT_SCHEMA_ID,
			'propertyDefinitions' => [
				'Status' => [
					'type' => 'select',
					'description' => '',
					'required' => false,
					'default' => null,
					'options' => [
						[ 'id' => 'opt1aaaaaaaaaaaa', 'label' => 'Active' ],
						[ 'id' => 'opt2aaaaaaaaaaaa', 'label' => 'Inactive' ],
					],
					'multiple' => false,
				],
			],
		] );

		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID, $json );
	}

	private function createRequestData( array $body ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [],
			'bodyContents' => json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
		] );
	}

	private function validBody(): array {
		return [
			'schema' => TestSubject::DEFAULT_SCHEMA_ID,
			'label' => 'ACME Corp',
			'statements' => [],
		];
	}

	private function newValidateSubjectApi(): ValidateSubjectApi {
		return new ValidateSubjectApi();
	}

}
