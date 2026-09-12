<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
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
	use NeoWikiMockAuthorityTrait;

	// A page id far above anything a fresh test database mints, so it resolves to no page.
	private const int NONEXISTENT_PAGE_ID = 999999;

	private const string SCHEMA = 'SetSubjectsOrderingApiTestSchema';
	private const string MAIN = 'sTestSso1111maa';
	private const string OTHER_1 = 'sTestSso1111ch1';
	private const string OTHER_2 = 'sTestSso1111ch2';
	private const string OTHER_3 = 'sTestSso1111ch3';
	private const string UNKNOWN = 'sTestSso1111zzz';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( self::SCHEMA );
	}

	public function testReordersOtherSubjects(): void {
		$pageId = $this->createPageWithMainAndOtherSubjects()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'otherSubjectIds' => [ self::OTHER_3, self::OTHER_1, self::OTHER_2 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'changed', $responseData['status'] );

		$saved = $this->savedSubjects( $pageId );
		$this->assertSame( self::MAIN, $saved->getMainSubject()?->id->text );
		$this->assertSame(
			[ self::OTHER_3, self::OTHER_1, self::OTHER_2 ],
			$saved->getOtherSubjects()->getIdsAsTextArray()
		);
	}

	public function testDemotesMainIntoChosenPosition(): void {
		$pageId = $this->createPageWithMainAndOtherSubjects()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => null,
				'otherSubjectIds' => [ self::OTHER_1, self::MAIN, self::OTHER_2, self::OTHER_3 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'changed', $responseData['status'] );

		$saved = $this->savedSubjects( $pageId );
		$this->assertNull( $saved->getMainSubject() );
		$this->assertSame(
			[ self::OTHER_1, self::MAIN, self::OTHER_2, self::OTHER_3 ],
			$saved->getOtherSubjects()->getIdsAsTextArray()
		);
	}

	public function testUnchangedOrderingCreatesNoRevision(): void {
		$revision = $this->createPageWithMainAndOtherSubjects();
		$pageId = $revision->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'otherSubjectIds' => [ self::OTHER_1, self::OTHER_2, self::OTHER_3 ],
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
		$pageId = $this->createPageWithMainAndOtherSubjects()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'otherSubjectIds' => [ self::OTHER_1, self::UNKNOWN, self::OTHER_3 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertStringContainsString( self::UNKNOWN, $responseData['message'] );
		$this->assertSame(
			[ self::OTHER_1, self::OTHER_2, self::OTHER_3 ],
			$this->savedSubjects( $pageId )->getOtherSubjects()->getIdsAsTextArray(),
			'A rejected ordering must leave the stored order untouched'
		);
	}

	public function testRejectsNonStringOtherSubjectId(): void {
		$pageId = $this->createPageWithMainAndOtherSubjects()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'otherSubjectIds' => [ self::OTHER_1, 42, self::OTHER_3 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertStringContainsString( 'otherSubjectIds', $responseData['message'] );
	}

	public function testRejectsBodyWithoutMainSubjectIdField(): void {
		$pageId = $this->createPageWithMainAndOtherSubjects()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'otherSubjectIds' => [ self::OTHER_1, self::OTHER_2, self::OTHER_3 ],
			] )
		);

		$responseData = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'error', $responseData['status'] );
		$this->assertStringContainsString( 'mainSubjectId', $responseData['message'] );
	}

	public function testCommentIsUsedAsEditSummary(): void {
		$pageId = $this->createPageWithMainAndOtherSubjects()->getPage()->getId();

		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'otherSubjectIds' => [ self::OTHER_3, self::OTHER_2, self::OTHER_1 ],
				'comment' => 'Reorder subjects via review test',
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame(
			'Reorder subjects via review test',
			$this->latestRevision( $pageId )->getComment()?->text
		);
	}

	public function testReadableButNotEditablePageReturns403(): void {
		$pageId = $this->createPageWithMainAndOtherSubjects()->getPage()->getId();

		// The caller can read the page - so its existence is already public - but cannot edit it.
		$response = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, [
				'mainSubjectId' => self::MAIN,
				'otherSubjectIds' => [ self::OTHER_3, self::OTHER_1, self::OTHER_2 ],
			] ),
			authority: $this->authorityWithGlobalEditButNoPageEdit()
		);

		$this->assertSame( 403, $response->getStatusCode() );
	}

	public function testUnreadablePageIsIndistinguishableFromNonexistentPage(): void {
		$pageId = $this->createPageWithMainAndOtherSubjects()->getPage()->getId();

		$body = [
			'mainSubjectId' => self::MAIN,
			'otherSubjectIds' => [ self::OTHER_3, self::OTHER_1, self::OTHER_2 ],
		];

		// A real page the caller may not read: a write to it must not reveal that it exists.
		$unreadable = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( $pageId, $body ),
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		// A page id that resolves to no page at all.
		$nonexistent = $this->executeHandler(
			$this->newApi(),
			$this->newRequest( self::NONEXISTENT_PAGE_ID, $body ),
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		$this->assertSame( 404, $unreadable->getStatusCode() );
		$this->assertSame( 404, $nonexistent->getStatusCode() );
		// Byte-identical: a caller sweeping page ids cannot tell a hidden page from an absent one.
		$this->assertSame(
			$nonexistent->getBody()->getContents(),
			$unreadable->getBody()->getContents()
		);
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

	private function createPageWithMainAndOtherSubjects(): RevisionRecord {
		return $this->createPageWithSubjects(
			'SetSubjectsOrderingApiTest_Page',
			mainSubject: $this->subject( self::MAIN, 'main' ),
			otherSubjects: new SubjectMap(
				$this->subject( self::OTHER_1, 'other one' ),
				$this->subject( self::OTHER_2, 'other two' ),
				$this->subject( self::OTHER_3, 'other three' ),
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
