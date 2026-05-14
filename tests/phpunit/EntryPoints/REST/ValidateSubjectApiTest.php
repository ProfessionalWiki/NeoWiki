<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ValidateSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ValidateSubjectApi
 * @group Database
 */
class ValidateSubjectApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;

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

	public function testEmptyLabelReturnsLabelRequiredViolation(): void {
		$this->createPages();

		$body = $this->validBody();
		$body['label'] = '';

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $body )
		);

		$responseBody = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertCount( 1, $responseBody['violations'] );
		$this->assertSame( 'label-required', $responseBody['violations'][0]['code'] );
		$this->assertNull( $responseBody['violations'][0]['propertyName'] );
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
		$this->createPages();

		$body = $this->validBody();
		$body['label'] = '';

		$response = $this->executeHandler(
			$this->newValidateSubjectApi(),
			$this->createRequestData( $body )
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

	private function createPages(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
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
		$instance = NeoWikiExtension::getInstance();
		return new ValidateSubjectApi(
			schemaLookup: $instance->getSchemaLookup(),
			subjectValidator: $instance->getSubjectValidator(),
			statementListBuilder: $instance->getStatementListBuilder(),
			selectStatementResolver: $instance->getSelectStatementResolver(),
		);
	}

}
