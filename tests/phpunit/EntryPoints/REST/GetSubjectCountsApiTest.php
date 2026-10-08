<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedRawQueryAuthorizer;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the integration base class for the pages the Subjects are saved on and the Neo4j test database the
 * counts are read from.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectCountsApi
 * @group Database
 */
class GetSubjectCountsApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( 'Computer' );
	}

	public function testCountsTheSubjectsOfEachSchema(): void {
		$this->createPageWithSubjects(
			'Home computers',
			TestSubject::build( id: 'sTestGSC1111111', schemaName: new SchemaName( 'Computer' ) ),
			new SubjectMap( TestSubject::build( id: 'sTestGSC1111112', schemaName: new SchemaName( 'Computer' ) ) )
		);

		$this->assertSame( '{"counts":{"Computer":2}}', $this->get()->getBody()->getContents() );
	}

	public function testAnswersAnEmptyObjectWhenNoSchemaHasSubjects(): void {
		$this->assertSame( '{"counts":{}}', $this->get()->getBody()->getContents() );
	}

	public function testDeniesACallerWithoutTheQueryRight(): void {
		$response = $this->executeHandler(
			NeoWikiExtension::newGetSubjectCountsApi(),
			self::request(),
			authority: $this->mockAnonAuthorityWithoutPermissions( [ AuthorityBasedRawQueryAuthorizer::RIGHT ] )
		);

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'permissionDenied', json_decode( $response->getBody()->getContents(), true )['errorType'] );
	}

	private function get(): ResponseInterface {
		return $this->executeHandler(
			NeoWikiExtension::newGetSubjectCountsApi(),
			self::request(),
			authority: $this->mockAnonAuthorityWithPermissions( [ AuthorityBasedRawQueryAuthorizer::RIGHT ] )
		);
	}

	private static function request(): RequestData {
		return new RequestData( [ 'method' => 'GET' ] );
	}

}
