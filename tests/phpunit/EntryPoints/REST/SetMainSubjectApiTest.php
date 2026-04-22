<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SetMainSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SetMainSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestSetMainSubjectPresenter
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject\SetMainSubjectAction
 * @group Database
 */
class SetMainSubjectApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use MockAuthorityTrait;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema(
			'SetMainSubjectApiTestSchema',
			'{"title":"SetMainSubjectApiTestSchema","propertyDefinitions":{}}'
		);
	}

	public function testPromotesChildToMain(): void {
		$pageId = $this->createPageWithMainAndChild()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [ 'subjectId' => 'sTestSMS1111ch1' ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertSame( 'changed', $body['status'] );

		$pageSubjects = NeoWikiExtension::getInstance()->getSubjectRepository()
			->getSubjectsByPageId( new PageId( $pageId ) );
		$this->assertSame( 'sTestSMS1111ch1', $pageSubjects->getMainSubject()?->id->text );
		$this->assertTrue( $pageSubjects->getChildSubjects()->hasSubject(
			TestSubject::build( id: 'sTestSMS1111maa' )->id
		) );
	}

	public function testClearsMainWithNullSubjectId(): void {
		$pageId = $this->createPageWithMainAndChild()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [ 'subjectId' => null ] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertSame( 'changed', $body['status'] );

		$pageSubjects = NeoWikiExtension::getInstance()->getSubjectRepository()
			->getSubjectsByPageId( new PageId( $pageId ) );
		$this->assertNull( $pageSubjects->getMainSubject() );
		$this->assertTrue( $pageSubjects->getChildSubjects()->hasSubject(
			TestSubject::build( id: 'sTestSMS1111maa' )->id
		) );
	}

	public function testReturnsNotFoundForUnknownSubjectId(): void {
		$pageId = $this->createPageWithMainAndChild()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [ 'subjectId' => 'sTestSMS1111zzz' ] )
		);

		$this->assertSame( 404, $response->getStatusCode() );
	}

	public function testReturnsBadRequestWhenSubjectIdMissing(): void {
		$pageId = $this->createPageWithMainAndChild()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [] )
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	public function testPermissionDenied(): void {
		$pageId = $this->createPageWithMainAndChild()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [ 'subjectId' => 'sTestSMS1111ch1' ] ),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$this->assertSame( 403, $response->getStatusCode() );
	}

	private function newApi(): SetMainSubjectApi {
		$csrfStub = $this->createStub( CsrfValidator::class );
		$csrfStub->method( 'verifyCsrfToken' )->willReturn( true );
		return new SetMainSubjectApi( csrfValidator: $csrfStub );
	}

	private function newRequest( int $pageId, array $body ): RequestData {
		return new RequestData( [
			'method' => 'PUT',
			'pathParams' => [ 'pageId' => (string)$pageId ],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( $body ),
		] );
	}

	private function createPageWithMainAndChild(): RevisionRecord {
		return $this->createPageWithSubjects(
			'SetMainSubjectApiTest_Page',
			mainSubject: TestSubject::build(
				id: 'sTestSMS1111maa',
				label: new SubjectLabel( 'main' ),
				schemaName: new SchemaName( 'SetMainSubjectApiTestSchema' )
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestSMS1111ch1',
					label: new SubjectLabel( 'child' ),
					schemaName: new SchemaName( 'SetMainSubjectApiTestSchema' )
				)
			)
		);
	}

}
