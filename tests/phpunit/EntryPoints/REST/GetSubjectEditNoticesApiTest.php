<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectEditNoticesApi;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectEditNoticesApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectEditNoticesPresenter
 * @covers \ProfessionalWiki\NeoWiki\Application\Queries\GetSubjectEditNotices\GetSubjectEditNoticesQuery
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\TitleBasedSubjectEditNoticeContextFactory
 * @group Database
 */
class GetSubjectEditNoticesApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;

	private function createNoticeMessage( string $messageKey, string $content ): void {
		$this->insertPage( 'MediaWiki:' . ucfirst( $messageKey ), $content );
		$this->getServiceContainer()->getMessageCache()->enable();
	}

	private function createPage( string $title ): int {
		return $this->insertPage( $title, 'Just wikitext.' )['id'];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function requestNotices( int $pageId, ?string $schemaName = null, ?Authority $authority = null ): array {
		$queryParams = $schemaName === null ? [] : [ 'schema' => $schemaName ];

		return json_decode(
			$this->executeHandler(
				new GetSubjectEditNoticesApi(),
				new RequestData( [
					'method' => 'GET',
					'pathParams' => [ 'pageId' => (string)$pageId ],
					'queryParams' => $queryParams,
				] ),
				authority: $authority
			)->getBody()->getContents(),
			true
		);
	}

	/**
	 * @return string[]
	 */
	private function noticeKeys( array $body ): array {
		return array_column( $body['notices'], 'key' );
	}

	/**
	 * The default HandlerTestTrait session is persistent, which is the case MediaWiki overwrites.
	 */
	public function testNoticesAreNeverStored(): void {
		$handler = new GetSubjectEditNoticesApi();

		$response = $this->executeHandler(
			$handler,
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$this->createPage( 'GetSubjectEditNoticesApiTest_Uncached' ) ],
			] )
		);
		$handler->applyCacheControl( $response );

		$this->assertSame( 'private,no-store', $response->getHeaderLine( 'Cache-Control' ) );
	}

	public function testPageWithoutNoticesYieldsAnEmptyList(): void {
		$pageId = $this->createPage( 'GetSubjectEditNoticesApiTest_Plain' );

		$this->assertSame( [ 'notices' => [] ], $this->requestNotices( $pageId ) );
	}

	public function testNamespaceAndPageNoticesAreReturned(): void {
		$pageId = $this->createPage( 'GetSubjectEditNoticesApiTest_Noticed' );
		$this->createNoticeMessage( 'neowiki-editnotice-0', 'Namespace wide' );
		$this->createNoticeMessage( 'neowiki-editnotice-0-GetSubjectEditNoticesApiTest_Noticed', 'This page' );

		$this->assertSame(
			[ 'neowiki-editnotice-0', 'neowiki-editnotice-0-GetSubjectEditNoticesApiTest_Noticed' ],
			$this->noticeKeys( $this->requestNotices( $pageId ) )
		);
	}

	public function testSchemaNoticeIsReturnedOnlyWhenTheSchemaIsNamed(): void {
		$pageId = $this->createPage( 'GetSubjectEditNoticesApiTest_Schema' );
		$this->createNoticeMessage( 'neowiki-editnotice-schema-Person', 'Editing a Person' );

		$this->assertSame( [], $this->noticeKeys( $this->requestNotices( $pageId ) ) );
		$this->assertSame(
			[ 'neowiki-editnotice-schema-Person' ],
			$this->noticeKeys( $this->requestNotices( $pageId, schemaName: 'Person' ) )
		);
	}

	public function testNoticeBodyIsRenderedHtml(): void {
		$pageId = $this->createPage( 'GetSubjectEditNoticesApiTest_Html' );
		$this->createNoticeMessage( 'neowiki-editnotice-0', "'''Approval needed'''" );

		$html = $this->requestNotices( $pageId )['notices'][0]['html'];

		$this->assertStringContainsString( '<b>Approval needed</b>', $html );
	}

	public function testNoticesOnAnUnreadablePageAreAbsent(): void {
		$restrictedPageId = $this->createPage( 'GetSubjectEditNoticesApiTest_Restricted' );
		$plainPageId = $this->createPage( 'GetSubjectEditNoticesApiTest_NoNotices' );
		$this->createNoticeMessage( 'neowiki-editnotice-0', 'Namespace wide' );

		$denied = $this->requestNotices(
			$restrictedPageId,
			authority: $this->authorityWithGlobalReadButNoPageRead()
		);

		// The page carries a notice and the plain one does not, yet a denied read must be
		// indistinguishable from a page that simply has nothing to show.
		$this->assertSame( [ 'notices' => [] ], $denied );
		$this->assertNotSame( [], $this->noticeKeys( $this->requestNotices( $plainPageId ) ) );
	}

	public function testMissingPageYieldsAnEmptyList(): void {
		$this->assertSame( [ 'notices' => [] ], $this->requestNotices( 999999 ) );
	}

}
