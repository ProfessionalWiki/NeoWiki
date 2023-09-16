<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SaveSchemaApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Presentation\RestSaveSchemaPresenter;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction
 * @group database
 */
class SaveSchemaApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use MockAuthorityTrait;

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
			$this->newSaveSchemaApi( true ),
			$this->createValidRequestData( $testContent )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertNotEmpty( $responseData[ 'success' ] );
		$this->assertTrue( $responseData[ 'success' ] );
		$this->assertNotEmpty( $responseData[ 'data' ]['propertyDefinitions'] );
		$this->assertCount( 1, $responseData[ 'data' ][ 'propertyDefinitions' ] );
	}

	public function testUpdateSchema(): void {
		$testContent = [
			'description' => 'Description bbb',
			'propertyDefinitions' => [
				"bbb" => [
					"type" => "string",
					"format" => "url"
				]
			]
		];

		$response = $this->executeHandler(
			$this->newSaveSchemaApi(),
			$this->createValidRequestData( $testContent )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertNotEmpty( $responseData[ 'success' ] );
		$this->assertTrue( $responseData[ 'success' ] );
		$this->assertNotEmpty( $responseData[ 'data' ][ 'description' ] );
		$this->assertEquals( 'Description bbb', $responseData[ 'data' ][ 'description' ] );
		$this->assertNotEmpty( $responseData[ 'data' ]['propertyDefinitions'] );
		$this->assertCount( 1, $responseData[ 'data' ][ 'propertyDefinitions' ] );
		$this->assertEquals( 'bbb', array_key_first( $responseData[ 'data' ][ 'propertyDefinitions' ] ) );
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
			$this->newSaveSchemaApi( true ),
			$this->createValidRequestData( $testContent )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertFalse( $responseData[ 'success' ] );
		$this->assertSame( 'The schema name "Test schema" already exists.', $responseData['message'] );
	}

	public function testInvalidData(): void {
		$testFormat = "invalid format value";
		$testContent = [
			'description' => 'Description aaa',
			'propertyDefinitions' => [
				"aaa" => [
					"type" => "string",
					"format" => $testFormat
				]
			]
		];

		$response = $this->executeHandler(
			$this->newSaveSchemaApi(),
			$this->createValidRequestData( $testContent )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertFalse( $responseData[ 'success' ] );
		$this->assertSame( 'Unknown format: ' . $testFormat, $responseData['message'] );
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
			$this->newSaveSchemaApi( true ),
			$this->createValidRequestData( $testContent ),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertFalse( $responseData[ 'success' ] );
		$this->assertSame( 'You do not have the necessary permissions to create the schema', $responseData['message'] );
	}

	private function newSaveSchemaApi( bool $isFirstSaving = false ): SaveSchemaApi {
		$csrfValidatorStub = $this->createStub( CsrfValidator::class );
		$csrfValidatorStub->method( 'verifyCsrfToken' )->willReturn( true );

		$presenter = new RestSaveSchemaPresenter();

		return new SaveSchemaApi(
			$presenter,
			NeoWikiExtension::getInstance()->newSaveSchemaAction( $presenter ),
			$csrfValidatorStub,
			$isFirstSaving
		);
	}

	private function createValidRequestData( array $content, bool $isFirstSaving = false ): RequestData {
		return new RequestData( [
			'method' => $isFirstSaving ? 'POST' : 'PUT',
			'pathParams' => [
				'schemaName' => 'Test schema'
			],
			'bodyContents' => json_encode( $content ),
			'headers' => [
				'Content-Type' => 'application/json'
			]
		] );
	}
}
