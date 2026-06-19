<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SetSubjectsOrderingApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SetSubjectsOrderingApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestSetSubjectsOrderingPresenter
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering\SetSubjectsOrderingAction
 * @group Database
 */
class SetSubjectsOrderingApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use MockAuthorityTrait;

	private const string SCHEMA = 'SetSubjectsOrderingApiTestSchema';
	private const string MAIN = 'sTestSso1111maa';
	private const string CHILD_1 = 'sTestSso1111ch1';
	private const string CHILD_2 = 'sTestSso1111ch2';
	private const string CHILD_3 = 'sTestSso1111ch3';
	private const string UNKNOWN = 'sTestSso1111zzz';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( self::SCHEMA );
	}

	public function testReordersChildSubjects(): void {
		$pageId = $this->createPageWithMainAndChildren()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'childSubjectIds' => [ self::CHILD_3, self::CHILD_1, self::CHILD_2 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'changed', $responseData['status'] );

		$saved = $this->savedSubjects( $pageId );
		$this->assertSame( self::MAIN, $saved->getMainSubject()?->id->text );
		$this->assertSame(
			[ self::CHILD_3, self::CHILD_1, self::CHILD_2 ],
			$saved->getChildSubjects()->getIdsAsTextArray()
		);
	}

	public function testDemotesMainIntoChosenPosition(): void {
		$pageId = $this->createPageWithMainAndChildren()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => null,
				'childSubjectIds' => [ self::CHILD_1, self::MAIN, self::CHILD_2, self::CHILD_3 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'changed', $responseData['status'] );

		$saved = $this->savedSubjects( $pageId );
		$this->assertNull( $saved->getMainSubject() );
		$this->assertSame(
			[ self::CHILD_1, self::MAIN, self::CHILD_2, self::CHILD_3 ],
			$saved->getChildSubjects()->getIdsAsTextArray()
		);
	}

	public function testUnchangedOrderingCreatesNoRevision(): void {
		$revision = $this->createPageWithMainAndChildren();
		$pageId = $revision->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'childSubjectIds' => [ self::CHILD_1, self::CHILD_2, self::CHILD_3 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'unchanged', $responseData['status'] );
		$this->assertSame(
			$revision->getId(),
			$this->latestRevision( $pageId )->getId(),
			'A no-op reorder must not create a new revision'
		);
	}

	public function testRejectsOrderingWithIdNotOnThePage(): void {
		$pageId = $this->createPageWithMainAndChildren()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'childSubjectIds' => [ self::CHILD_1, self::UNKNOWN, self::CHILD_3 ],
			] )
		);

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame(
			[ self::CHILD_1, self::CHILD_2, self::CHILD_3 ],
			$this->savedSubjects( $pageId )->getChildSubjects()->getIdsAsTextArray(),
			'A rejected ordering must leave the stored order untouched'
		);
	}

	public function testRejectsNonStringChildSubjectId(): void {
		$pageId = $this->createPageWithMainAndChildren()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'childSubjectIds' => [ self::CHILD_1, 42, self::CHILD_3 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertStringContainsString( 'childSubjectIds', $responseData['message'] );
	}

	public function testRejectsBodyWithoutMainSubjectIdField(): void {
		$pageId = $this->createPageWithMainAndChildren()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'childSubjectIds' => [ self::CHILD_1, self::CHILD_2, self::CHILD_3 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertStringContainsString( 'mainSubjectId', $responseData['message'] );
	}

	public function testCommentIsUsedAsEditSummary(): void {
		$pageId = $this->createPageWithMainAndChildren()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'childSubjectIds' => [ self::CHILD_3, self::CHILD_2, self::CHILD_1 ],
				'comment' => 'Reorder subjects via review test',
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame(
			'Reorder subjects via review test',
			$this->latestRevision( $pageId )->getComment()?->text
		);
	}

	public function testPermissionDenied(): void {
		$pageId = $this->createPageWithMainAndChildren()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'childSubjectIds' => [ self::CHILD_1, self::CHILD_2, self::CHILD_3 ],
			] ),
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);

		$this->assertSame( 403, $response->getStatusCode() );
	}

	private function newApi(): SetSubjectsOrderingApi {
		$csrfStub = $this->createStub( CsrfValidator::class );
		$csrfStub->method( 'verifyCsrfToken' )->willReturn( true );
		return new SetSubjectsOrderingApi( csrfValidator: $csrfStub );
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private function newRequest( int $pageId, array $body ): RequestData {
		return new RequestData( [
			'method' => 'PUT',
			'pathParams' => [ 'pageId' => (string)$pageId ],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( $body ),
		] );
	}

	private function createPageWithMainAndChildren(): RevisionRecord {
		return $this->createPageWithSubjects(
			'SetSubjectsOrderingApiTest_Page',
			mainSubject: $this->subject( self::MAIN, 'main' ),
			childSubjects: new SubjectMap(
				$this->subject( self::CHILD_1, 'child one' ),
				$this->subject( self::CHILD_2, 'child two' ),
				$this->subject( self::CHILD_3, 'child three' ),
			)
		);
	}

	private function subject( string $id, string $label ): Subject {
		return TestSubject::build(
			id: $id,
			label: new SubjectLabel( $label ),
			schemaName: new SchemaName( self::SCHEMA )
		);
	}

	private function savedSubjects( int $pageId ): PageSubjects {
		return NeoWikiExtension::getInstance()->getSubjectRepository()
			->getSubjectsByPageId( new PageId( $pageId ) );
	}

	private function latestRevision( int $pageId ): RevisionRecord {
		$revision = MediaWikiServices::getInstance()->getRevisionLookup()
			->getRevisionByPageId( $pageId );
		$this->assertNotNull( $revision );
		return $revision;
	}

}
