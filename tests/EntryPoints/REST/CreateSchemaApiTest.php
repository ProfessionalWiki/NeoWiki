<?php

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSchemaApi;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Presentation\RestCreateSchemaPresenter;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSchemaApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSchema\CreateSchemaAction
 * @group database
 */
class CreateSchemaApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use MockAuthorityTrait;

	private const SCHEMA_NAME = 'CreateSchemaApiTestSchema';

	public function setUp(): void {
		//$this->markPageTableAsUsed();
	}

	private function newCreateSchemaApi(): CreateSchemaApi {
		$csrfValidatorStub = $this->createStub( CsrfValidator::class );
		$csrfValidatorStub->method( 'verifyCsrfToken' )->willReturn( true );

		$presenter = new RestCreateSchemaPresenter();

		return new CreateSchemaApi(
			$presenter,
			$csrfValidatorStub
		);
	}

	private function createValidRequestData( array $schemaArray, string $schemaName = self::SCHEMA_NAME ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [
				'schemaName' => $schemaName
			],
			'bodyContents' => json_encode( $schemaArray ),
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}

	public function testCreateSchemaHappyPath(): void {
		$schemaArray = $this->newValidSchemaArray();

		$response = $this->executeHandler(
			$this->newCreateSchemaApi(),
			$this->createValidRequestData( $schemaArray )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertNotEmpty( $responseData[ 'success' ] );
		$this->assertTrue( $responseData[ 'success' ] );
		$this->assertNotEmpty( $responseData[ 'schema' ][ 'description' ] );
		$this->assertEquals( $schemaArray[ 'description' ], $responseData[ 'schema' ][ 'description' ] );
		$this->assertNotEmpty( $responseData[ 'schema' ][ 'propertyDefinitions' ] );
		$this->assertSame( $schemaArray[ 'propertyDefinitions' ], $responseData[ 'schema' ][ 'propertyDefinitions' ] );
		$this->assertCount( 1, $responseData[ 'schema' ][ 'propertyDefinitions' ] );
	}

	public function testCreatingExistingSchemaResultsInError(): void {
		$this->createSchema( self::SCHEMA_NAME );

		$response = $this->executeHandler(
			$this->newCreateSchemaApi(),
			$this->createValidRequestData( $this->newValidSchemaArray() )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertFalse( $responseData[ 'success' ] );
		$this->assertSame( 'A schema by that name has already been registered.', $responseData[ 'message' ] );
	}

	private function newValidSchemaArray(): array {
		return [
			'description' => 'Description',
			'propertyDefinitions' => [
				"aaa" => [
					"type" => "string",
					"format" => "email"
				]
			]
		];
	}

	public function testInvalidSchemaResultsInError(): void {
		$response = $this->executeHandler(
			$this->newCreateSchemaApi(),
			$this->createValidRequestData( $this->newInvalidSchemaArray(), self::SCHEMA_NAME . '2' )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertFalse( $responseData[ 'success' ] );
		$this->assertSame( 'Invalid parameters for the schema creation.', $responseData['message'] );
	}

	private function newInvalidSchemaArray(): array {
		return [
			'description' => 'Description',
			'propertyDefinitions' => [
				"aaa" => [
					"type" => "string",
					"wrong_format_key" => 'text' // Invalid
				]
			]
		];
	}

	public function testPermissionDenied(): void {
		$response = $this->executeHandler(
			$this->newCreateSchemaApi(),
			$this->createValidRequestData( $this->newValidSchemaArray() ),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertFalse( $responseData[ 'success' ] );
		$this->assertSame( 'You don\'t have permissions to create a schema.', $responseData['message'] );
	}

}
