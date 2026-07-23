<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ResolveSubjectIriApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use TestLogger;

/**
 * End-to-end proof that the on-wiki configuration page reaches a real consumer: the Subject IRI
 * dereference reads its target through the combining lookup, so a valid page value wins over the PHP
 * config, an invalid one falls back with a warning, and the kill switch stops the page from applying.
 *
 * @covers \ProfessionalWiki\NeoWiki\Application\WikiConfig\WikiConfigLookup
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::dereferenceSubjectsToDataTab
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiWikiConfigSource
 * @group Database
 */
class InWikiConfigDereferenceTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	private const string SCHEMA = 'InWikiConfigDerefSchema';
	private const string SUBJECT_ID = 'sCfgDeref111111';

	private int $pageId;

	public function setUp(): void {
		$this->setUpNeo4j();

		// Pin the PHP config to the default so a passing test proves the page value, not an ambient override.
		$this->overrideConfigValue( 'NeoWikiDereferenceSubjectsToDataTab', false );

		$this->createSchema( self::SCHEMA );

		$this->pageId = $this->createPageWithSubjects(
			'InWikiConfigDereference_City',
			mainSubject: TestSubject::build(
				id: self::SUBJECT_ID,
				label: new SubjectLabel( 'Berlin' ),
				schemaName: new SchemaName( self::SCHEMA ),
			),
		)->getPage()->getId();
	}

	protected function tearDown(): void {
		// Drop the memoized config-page source so a later test does not inherit it.
		NeoWikiExtension::resetInstance();

		parent::tearDown();
	}

	private function saveConfigPage( string $json ): void {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_MEDIAWIKI, NeoWikiExtension::CONFIG_PAGE_TITLE ) );

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new JsonContent( $json ) );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'config' ) );

		// The source memoizes the page per request, so drop the singleton to read the just-saved page.
		NeoWikiExtension::resetInstance();
	}

	private function htmlDereferenceLocation(): string {
		$response = $this->executeHandler(
			new ResolveSubjectIriApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'subjectId' => self::SUBJECT_ID ],
				'headers' => [ 'Accept' => 'text/html' ],
			] ),
		);

		$this->assertSame( 303, $response->getStatusCode() );

		return $response->getHeaderLine( 'Location' );
	}

	private function hostingPageUrl(): string {
		return Title::newFromID( $this->pageId )->getCanonicalURL();
	}

	public function testAValidPageSettingWinsOverThePhpConfig(): void {
		$this->saveConfigPage( '{ "dereferenceSubjectsToDataTab": true }' );

		$location = $this->htmlDereferenceLocation();

		$this->assertStringContainsString( 'action=subjects', $location, 'The redirect opens the Data tab.' );
		$this->assertStringEndsWith( '#' . self::SUBJECT_ID, $location, 'The redirect targets the Subject row.' );
	}

	public function testAnInvalidPageSettingFallsBackToThePhpConfigAndWarns(): void {
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );

		$this->saveConfigPage( '{ "dereferenceSubjectsToDataTab": "yes" }' );

		$location = $this->htmlDereferenceLocation();

		$this->assertSame( $this->hostingPageUrl(), $location, 'The dereference falls back to the PHP config target.' );
		$this->assertTrue( $this->loggedAWarning( $logger ), 'The invalid page value is logged.' );
	}

	public function testThePageSettingIsIgnoredWhenInWikiConfigIsDisabled(): void {
		$this->saveConfigPage( '{ "dereferenceSubjectsToDataTab": true }' );
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		$this->assertSame(
			$this->hostingPageUrl(),
			$this->htmlDereferenceLocation(),
			'With the kill switch off the page is not read, so the PHP config target applies.'
		);
	}

	private function loggedAWarning( TestLogger $logger ): bool {
		foreach ( $logger->getBuffer() as [ $level ] ) {
			if ( $level === 'warning' ) {
				return true;
			}
		}

		return false;
	}

}
