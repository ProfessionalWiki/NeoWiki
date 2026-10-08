<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SaveSchemaApi;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SaveSchemaApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SchemaPageWriteApi
 * @group Database
 */
class SaveSchemaApiTest extends NeoWikiIntegrationTestCase {

	use RunsSchemaWriteEndpoints;

	private const string SCHEMA_JSON = <<<JSON
		{
			"description": "Someone the museum works with",
			"propertyDefinitions": {
				"Age": { "type": "number", "minimum": 0 }
			}
		}
		JSON;

	private const string CHANGED_SCHEMA_JSON = <<<JSON
		{
			"description": "Someone the museum works with",
			"propertyDefinitions": {
				"Website": { "type": "url", "multiple": true }
			}
		}
		JSON;

	public function testCreatesTheSchemaPage(): void {
		$response = $this->save( 'Person', self::SCHEMA_JSON );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertSame( 'NeoWikiSchema', $this->getSchemaPageContentModel( 'Person' ) );
		$this->assertJsonStringEqualsJsonString( self::SCHEMA_JSON, $this->getSchemaPageText( 'Person' ) );
	}

	public function testReplacesAnExistingSchema(): void {
		$this->createSchema( 'Person', self::SCHEMA_JSON );

		$response = $this->save( 'Person', self::CHANGED_SCHEMA_JSON );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertJsonStringEqualsJsonString( self::CHANGED_SCHEMA_JSON, $this->getSchemaPageText( 'Person' ) );
	}

	public function testRespondsWithTheSchemaAsTheReadEndpointServesIt(): void {
		$response = $this->save( 'Person', self::SCHEMA_JSON );

		$this->assertJsonStringEqualsJsonString( $this->readSchemaFromApi( 'Person' ), $this->bodyOf( $response ) );
	}

	public function testRespondsWithTheSavedSchemaWhileAnotherRevisionIsPublished(): void {
		$approved = $this->createSchema( 'Person', self::SCHEMA_JSON );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );

		$response = $this->save( 'Person', self::CHANGED_SCHEMA_JSON );

		$this->assertSame(
			[ 'Website' ],
			array_keys( json_decode( $this->bodyOf( $response ), true )['schema']['propertyDefinitions'] )
		);
	}

	public function testSchemaReadFromTheReadEndpointSavesBackUnchanged(): void {
		$this->createSchema( 'Person', <<<JSON
			{
				"propertyDefinitions": {
					"Name": { "type": "text", "required": { "severity": "error" }, "maxLength": 80 },
					"Status": { "type": "select", "options": [ { "id": "o1", "label": "Active" } ] },
					"Employer": { "type": "relation", "relation": "Works for", "targetSchema": "Company" },
					"Retired": { "type": "boolean", "default": false }
				}
			}
			JSON
		);
		$servedBefore = $this->readSchemaFromApi( 'Person' );

		$response = $this->save( 'Person', (string)json_encode( json_decode( $servedBefore )->schema ) );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertJsonStringEqualsJsonString( $servedBefore, $this->readSchemaFromApi( 'Person' ) );
	}

	public function testSchemaWithoutPropertiesKeepsAnEmptyObject(): void {
		$response = $this->save( 'Person', '{ "propertyDefinitions": {} }' );

		$this->assertSame( 201, $response->getStatusCode() );
		$this->assertJsonStringEqualsJsonString(
			'{ "schema": { "description": "", "propertyDefinitions": {} } }',
			$this->bodyOf( $response )
		);
	}

	public function testDecomposedCharactersAreStoredComposed(): void {
		$this->save( 'Person', '{ "propertyDefinitions": { "Cafe' . "\u{0301}" . '": { "type": "text" } } }' );

		$this->assertSame(
			[ "Caf\u{00E9}" ],
			array_keys( json_decode( $this->getSchemaPageText( 'Person' ), true )['propertyDefinitions'] )
		);
	}

	public function testInvalidSchemaIsRefusedWithTheValidatorsVerdict(): void {
		$response = $this->save( 'Person', '{ "propertyDefinitions": { "Age": { "minimum": 0 } } }' );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'neowiki-schema-invalid', $this->errorKeyOf( $response ) );
		$this->assertFalse( $this->schemaPageExists( 'Person' ) );
	}

	public function testRequestWithoutCsrfTokenIsRefused(): void {
		$response = $this->executeAs(
			$this->getTestSysop()->getUser(),
			new SaveSchemaApi( csrfValidator: $this->newTokenlessCsrfValidator() ),
			$this->newPutRequest( 'Person', self::SCHEMA_JSON )
		);

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertFalse( $this->schemaPageExists( 'Person' ) );
	}

	public function testUserWhoMayNotEditSchemasIsRefused(): void {
		$this->setGroupPermissions( 'user', 'neowiki-schema-edit', false );

		$response = $this->saveAs( $this->getTestUser()->getUser(), 'Person', self::SCHEMA_JSON );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertFalse( $this->schemaPageExists( 'Person' ) );
	}

	public function testUserWhoMayNotEditAProtectedSchemaIsRefused(): void {
		$this->createSchema( 'Person', self::SCHEMA_JSON );
		$this->protectSchemaPage( 'Person' );

		$response = $this->saveAs( $this->getTestUser()->getUser(), 'Person', self::CHANGED_SCHEMA_JSON );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertJsonStringEqualsJsonString( self::SCHEMA_JSON, $this->getSchemaPageText( 'Person' ) );
	}

	public function testEditFilterRefusalBlocksTheSave(): void {
		$this->setTemporaryHook(
			'EditFilterMergedContent',
			static function ( $context, $content, Status $status ): bool {
				$status->fatal( 'spamprotectiontext' );
				return false;
			}
		);

		$response = $this->save( 'Person', self::SCHEMA_JSON );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'spamprotectiontext', $this->errorKeyOf( $response ) );
		$this->assertFalse( $this->schemaPageExists( 'Person' ) );
	}

	public function testEditAnExtensionHoldsBackIsNotReportedAsSaved(): void {
		$this->setTemporaryHook(
			'EditFilterMergedContent',
			static function ( $context, $content, Status $status ): bool {
				$status->statusData = [ 'captcha' => [ 'type' => 'question', 'question' => '1 + 1?' ] ];
				return false;
			}
		);

		$response = $this->save( 'Person', self::SCHEMA_JSON );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'hookaborted', $this->errorKeyOf( $response ) );
	}

	public function testNameThatIsNoPageTitleIsRefused(): void {
		$response = $this->save( 'Per[son]', self::SCHEMA_JSON );

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testNameWithASectionDoesNotReachThePage(): void {
		$response = $this->save( 'Person#Contact', self::SCHEMA_JSON );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertFalse( $this->schemaPageExists( 'Person' ) );
	}

	public function testNameCannotReachAPageOutsideTheSchemaNamespace(): void {
		$this->save( 'Help:Person', self::SCHEMA_JSON );

		$this->assertFalse( Title::makeTitle( NS_HELP, 'Person' )->exists( IDBAccessObject::READ_LATEST ) );
		$this->assertTrue( $this->schemaPageExists( 'Help:Person' ) );
	}

	public function testSchemaYouMayNotReadAnswersNotFound(): void {
		$this->denyReadingSchemaPage( 'Person' );

		$response = $this->save( 'Person', self::SCHEMA_JSON );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertFalse( $this->schemaPageExists( 'Person' ) );
	}

	public function testCommentBecomesTheEditSummary(): void {
		$this->save( 'Person', self::SCHEMA_JSON, comment: 'Track ages' );

		$this->assertSame(
			'Track ages',
			$this->getServiceContainer()->getRevisionLookup()
				->getRevisionByTitle( $this->schemaTitle( 'Person' ) )
				?->getComment()?->text
		);
	}

	private function save( string $schemaName, string $schemaJson, ?string $comment = null ): ResponseInterface {
		return $this->saveAs( $this->getTestSysop()->getUser(), $schemaName, $schemaJson, $comment );
	}

	private function saveAs( User $user, string $schemaName, string $schemaJson, ?string $comment = null ): ResponseInterface {
		return $this->executeAs(
			$user,
			new SaveSchemaApi( csrfValidator: $this->newCsrfValidatorStub() ),
			$this->newPutRequest( $schemaName, $schemaJson, $comment )
		);
	}

	private function newPutRequest( string $schemaName, string $schemaJson, ?string $comment = null ): RequestData {
		$body = '{ "schema": ' . $schemaJson
			. ( $comment === null ? '' : ', "comment": ' . json_encode( $comment ) )
			. ' }';

		return new RequestData( [
			'method' => 'PUT',
			'pathParams' => [ 'schemaName' => $schemaName ],
			'bodyContents' => $body,
			'headers' => [ 'Content-Type' => 'application/json' ],
		] );
	}

	private function protectSchemaPage( string $schemaName ): void {
		$cascade = false;

		$this->assertStatusGood(
			$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $this->schemaTitle( $schemaName ) )
				->doUpdateRestrictions(
					[ 'edit' => 'sysop' ],
					[],
					$cascade,
					'Only administrators change this Schema',
					$this->getTestSysop()->getUser()
				)
		);
	}

	private function readSchemaFromApi( string $schemaName ): string {
		return $this->bodyOf( $this->executeHandler(
			new GetSchemaApi(),
			new RequestData( [ 'method' => 'GET', 'pathParams' => [ 'schemaName' => $schemaName ] ] )
		) );
	}

	private function errorKeyOf( ResponseInterface $response ): ?string {
		return json_decode( $this->bodyOf( $response ), true )['errorKey'] ?? null;
	}

	private function getSchemaPageText( string $schemaName ): string {
		return $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $this->schemaTitle( $schemaName ) )
			->getContent()
			?->serialize() ?? '';
	}

	private function getSchemaPageContentModel( string $schemaName ): string {
		return $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $this->schemaTitle( $schemaName ) )
			->getContentModel();
	}

}
