<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MockHttpTrait;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Proves that a configured SPARQL store participates in the real hook-facing write path: a page edit
 * that stores a Subject sends the store its DROP/INSERT update, a deletion sends the DROP, and a
 * failing store does not abort the edit (the per-plugin failure isolation covers the new plugin). The
 * HTTP layer is faked, so no live SPARQL endpoint is needed.
 *
 * Neo4j is switched off for these tests (runWithoutGraphBackend), so the SPARQL plugin is exercised on
 * its own and the assertions do not depend on a live Neo4j.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\SparqlProjectionStore
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlUpdateEndpoint
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\SparqlPlugin
 * @group Database
 */
class SparqlGraphProjectionTest extends NeoWikiIntegrationTestCase {

	use MockHttpTrait;

	private const string ENDPOINT = 'https://qlever.example/api/neowiki';
	private const string SECOND_ENDPOINT = 'https://qlever.example/api/mirror';

	/**
	 * @var array<int, array{url: string, body: string}>
	 */
	private array $captured = [];

	protected function setUp(): void {
		parent::setUp();
		$this->captured = [];
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	public function testConfiguredSparqlStoreReceivesPageEditAsGraphUpdate(): void {
		$this->installMockHttp( $this->capturingHttp() );
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [ [ 'updateUrl' => self::ENDPOINT ] ] );

		$pageId = $this->runWithoutGraphBackend(
			fn (): int => $this->createPageWithSubjects( 'Sparql projection page', TestSubject::build() )->getPageId()
		);

		$update = $this->lastUpdateFor( self::ENDPOINT );
		$this->assertStringContainsString( 'INSERT DATA', $update );
		$this->assertStringContainsString( '/graph/native/page/' . $pageId, $update );
		$this->assertStringContainsString( '/entity/' . TestSubject::ZERO_GUID, $update );
	}

	public function testEveryConfiguredSparqlStoreReceivesThePageEdit(): void {
		$this->installMockHttp( $this->capturingHttp() );
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [
			[ 'updateUrl' => self::ENDPOINT ],
			[ 'updateUrl' => self::SECOND_ENDPOINT ],
		] );

		$pageId = $this->runWithoutGraphBackend(
			fn (): int => $this->createPageWithSubjects( 'Sparql fan out page', TestSubject::build() )->getPageId()
		);

		$this->assertReceivedInsertForPage( self::ENDPOINT, $pageId );
		$this->assertReceivedInsertForPage( self::SECOND_ENDPOINT, $pageId );
	}

	public function testDeletingPageSendsDropToSparqlStore(): void {
		$this->installMockHttp( $this->capturingHttp() );
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [ [ 'updateUrl' => self::ENDPOINT ] ] );

		$pageId = $this->runWithoutGraphBackend( function (): int {
			$pageId = $this->createPageWithSubjects( 'Sparql delete page', TestSubject::build() )->getPageId();
			$this->captured = [];
			$this->deletePageByName( 'Sparql delete page' );
			return $pageId;
		} );

		$update = $this->lastUpdateFor( self::ENDPOINT );
		$this->assertStringContainsString( 'DROP SILENT GRAPH', $update );
		$this->assertStringContainsString( '/graph/native/page/' . $pageId, $update );
		$this->assertStringNotContainsString( 'INSERT DATA', $update );
	}

	public function testFailingSparqlEndpointDoesNotAbortTheEdit(): void {
		$this->installMockHttp( $this->makeFakeHttpRequest( 'server error', 500 ) );
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [ [ 'updateUrl' => self::ENDPOINT ] ] );

		$revision = $this->runWithoutGraphBackend(
			fn () => $this->createPageWithSubjects( 'Sparql failing endpoint page', TestSubject::build() )
		);

		$this->assertNotNull( $revision, 'the edit must still commit despite the SPARQL endpoint failing' );
		$this->assertGreaterThan( 0, $revision->getPageId() );
	}

	private function capturingHttp(): callable {
		return function ( $url, $options = [] ) {
			$this->captured[] = [ 'url' => $url, 'body' => $options['postData'] ?? '' ];
			return $this->makeFakeHttpRequest( '{}', 200 );
		};
	}

	private function lastUpdateFor( string $url ): string {
		$bodies = [];

		foreach ( $this->captured as $request ) {
			if ( $request['url'] === $url ) {
				$bodies[] = $request['body'];
			}
		}

		$this->assertNotEmpty( $bodies, 'expected a SPARQL update to be posted to ' . $url );

		return (string)end( $bodies );
	}

	private function assertReceivedInsertForPage( string $endpoint, int $pageId ): void {
		$update = $this->lastUpdateFor( $endpoint );
		$this->assertStringContainsString( 'INSERT DATA', $update );
		$this->assertStringContainsString( '/graph/native/page/' . $pageId, $update );
	}

	private function deletePageByName( string $pageName ): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage( $page, $this->getTestSysop()->getUser() );

		$status = $deletePage->deleteUnsafe( 'test cleanup' );
		$this->assertStatusGood( $status );

		DeferredUpdates::doUpdates();
	}

}
