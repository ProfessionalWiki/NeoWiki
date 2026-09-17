<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetJsonSchemaApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetJsonSchemaApi
 * @group Database
 */
class GetJsonSchemaApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	private const string ROUTE = '/neowiki/v0/schema/{schemaName}/json-schema';
	private const string SCHEMA_NAME = 'GetJsonSchemaApiTestSchema';
	private const string ABSENT_SCHEMA_NAME = 'GetJsonSchemaApiTestAbsentSchema';

	public function setUp(): void {
		$this->createSchema( self::SCHEMA_NAME, <<<JSON
{
	"description": "A test schema",
	"propertyDefinitions": {
		"Founded at": { "type": "number", "minimum": 1800 }
	}
}
JSON
		);
	}

	public function testServesTheSchemaAsAJsonSchemaDocument(): void {
		$response = $this->get( self::SCHEMA_NAME );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'application/schema+json', $response->getHeaderLine( 'Content-Type' ) );

		$document = $this->decode( $response );

		$this->assertMatchesRegularExpression( '#^https?://#', $document['$id'] );
		$this->assertSame(
			[ 'type' => 'number', 'minimum' => 1800 ],
			$document['properties']['statements']['properties']['Founded at']['properties']['value']
		);
	}

	public function testDocumentNamesTheSchemaAsItsPageIsTitled(): void {
		// The Schema's name is its page's name (ADR 17), and Subjects carry that name. A document
		// fetched under any other spelling of the title must still describe Subjects of the Schema
		// it served, so `title` and the `schema` const cannot echo the path back.
		$document = $this->decode( $this->get( '_' . lcfirst( self::SCHEMA_NAME ) . '_' ) );

		$this->assertSame( self::SCHEMA_NAME, $document['title'] );
		$this->assertSame( self::SCHEMA_NAME, $document['properties']['schema']['const'] );
		$this->assertStringEndsWith( '/neowiki/v0/schema/' . self::SCHEMA_NAME . '/json-schema', $document['$id'] );
	}

	public function testAbsentSchemaIsNotFound(): void {
		$response = $this->get( self::ABSENT_SCHEMA_NAME );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertStringContainsString(
			self::ABSENT_SCHEMA_NAME,
			$response->getBody()->getContents(),
			'A body naming no Schema would let testSchemaOnAnUnreadablePageAnswersLikeAnAbsentSchema pass vacuously.'
		);
	}

	public function testReservedSchemaNameIsNotFound(): void {
		$this->assertSame( 404, $this->get( 'page' )->getStatusCode() );
	}

	public function testSchemaOnAnUnreadablePageAnswersLikeAnAbsentSchema(): void {
		// A Schema the caller may not read must be indistinguishable from one that does not exist,
		// so the endpoint cannot be used to confirm that a restricted Schema is there (#1046).
		$restricted = $this->authorityWithGlobalReadButNoPageRead();

		$denied = $this->get( self::SCHEMA_NAME, $restricted );
		$absent = $this->get( self::ABSENT_SCHEMA_NAME, $restricted );

		$this->assertSame( 404, $denied->getStatusCode() );
		$this->assertSame(
			str_replace( self::ABSENT_SCHEMA_NAME, self::SCHEMA_NAME, $absent->getBody()->getContents() ),
			$denied->getBody()->getContents()
		);
	}

	private function get( string $schemaName, ?Authority $authority = null ): Response {
		// The Schema read gate runs against the request's Authority, which the handler reads from the
		// main context rather than from the one executeHandler injects. Always setting one makes every
		// test expecting a 200 the control for the denial test above.
		RequestContext::getMain()->setAuthority( $authority ?? $this->mockRegisteredUltimateAuthority() );
		NeoWikiExtension::resetInstance();

		return $this->executeHandler(
			new GetJsonSchemaApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'schemaName' => $schemaName ],
			] ),
			// The document's $id is built from the route the handler is bound to, which only a
			// registered handler knows.
			config: [ 'path' => self::ROUTE ]
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decode( Response $response ): array {
		return json_decode( $response->getBody()->getContents(), true );
	}

}
