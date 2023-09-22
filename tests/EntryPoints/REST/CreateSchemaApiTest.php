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

	private function newCreateSchemaApi(): CreateSchemaApi {
		$csrfValidatorStub = $this->createStub( CsrfValidator::class );
		$csrfValidatorStub->method( 'verifyCsrfToken' )->willReturn( true );

		$presenter = new RestCreateSchemaPresenter();

		return new CreateSchemaApi(
			$presenter,
			$csrfValidatorStub
		);
	}

	private function createValidRequestData( array $content, ?bool $name = null ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [
				'schemaName' => $name ?: 'Test schema'
			],
			'bodyContents' => json_encode( $content ),
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}

	public function testCreateSchema(): void {
		$testContent = [
			'description' => 'Description aaa',
			'propertyDefinitions' => [
				"aaa" => [
					"type" => "string",
					"format" => "email"
				]
			]
		];

		$response = $this->executeHandler(
			$this->newCreateSchemaApi(),
			$this->createValidRequestData( $testContent )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertNotEmpty( $responseData[ 'success' ] );
		$this->assertTrue( $responseData[ 'success' ] );
		$this->assertNotEmpty( $responseData[ 'schema' ][ 'description' ] );
		$this->assertEquals( $testContent[ 'description' ], $responseData[ 'schema' ][ 'description' ] );
		$this->assertNotEmpty( $responseData[ 'schema' ][ 'propertyDefinitions' ] );
		$this->assertSame( $testContent[ 'propertyDefinitions' ], $responseData[ 'schema' ][ 'propertyDefinitions' ] );
		$this->assertCount( 1, $responseData[ 'schema' ][ 'propertyDefinitions' ] );
	}

	public function testSchemaExists(): void {
		$testContent = [
			'description' => 'Description aaa',
			'propertyDefinitions' => [
				"aaa" => [
					"type" => "string",
					"format" => "email"
				]
			]
		];

		$response = $this->executeHandler(
			$this->newCreateSchemaApi( true ),
			$this->createValidRequestData( $testContent )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertFalse( $responseData[ 'success' ] );
		$this->assertSame( 'A schema by that name has already been registered.', $responseData[ 'message' ] );
	}

	public function testInvalidData(): void {
		$testContent = [
			'description' => 'Description aaa',
			'propertyDefinitions' => [
				"aaa" => [
					"type" => "string",
					"formatrrr" => 'sss'
				]
			]
		];

		$response = $this->executeHandler(
			$this->newCreateSchemaApi(),
			$this->createValidRequestData( $testContent, 'Test schema 2' )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertFalse( $responseData[ 'success' ] );
		$this->assertSame( 'Invalid parameters for the schema creation.', $responseData['message'] );
	}

	public function testPermissionDenied(): void {
		$testContent = [
			'description' => 'Description that is really ',
			'propertyDefinitions' => [
				"aaa" => [
					"type" => "string",
					"format" => "email"
				]
			]
		];

		$response = $this->executeHandler(
			$this->newCreateSchemaApi( true ),
			$this->createValidRequestData( $testContent ),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertFalse( $responseData[ 'success' ] );
		$this->assertSame( 'You don\'t have permissions to create a schema.', $responseData['message'] );
	}
}
