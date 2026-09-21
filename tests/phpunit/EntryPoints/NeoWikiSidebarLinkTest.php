<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onSidebarBeforeOutput
 * @group Database
 */
class NeoWikiSidebarLinkTest extends NeoWikiIntegrationTestCase {

	private const NEOWIKI_SECTION = 'neowiki-page-tools-label';

	public function testOverviewLinkIsListedFirst(): void {
		$section = $this->neoWikiSectionOnAnOrdinaryPage();
		$link = reset( $section );

		$this->assertSame( 't-neowiki-overview', $link['id'] );
		$this->assertSame( 'Overview', $link['text'] );
		$this->assertStringContainsString( 'NeoWiki', $link['href'] );
	}

	public function testOverviewLinkIsShownToAUserWhoMayNotCreatePages(): void {
		$this->setGroupPermissions( '*', 'createpage', false );
		$this->setGroupPermissions( 'user', 'createpage', false );

		$this->assertNotNull(
			$this->findLinkById( $this->neoWikiSectionOnAnOrdinaryPage(), 't-neowiki-overview' )
		);
	}

	public function testOverviewLinkIsShownOutsideTheContentNamespaces(): void {
		$sidebar = $this->buildSidebar( Title::makeTitle( NS_HELP, 'Example' ) );

		$this->assertNotNull( $this->findLinkById( $sidebar[self::NEOWIKI_SECTION] ?? [], 't-neowiki-overview' ) );
	}

	public function testAllSchemasLinkIsPlacedInTheNeoWikiSection(): void {
		$this->assertAllPagesLinkInNeoWikiSection(
			namespace: NeoWikiExtension::NS_SCHEMA,
			linkId: 't-neowiki-schemas',
			text: 'All schemas',
			hrefContains: 'Schemas',
			hrefExcludes: 'Layouts'
		);
	}

	public function testAllLayoutsLinkIsPlacedInTheNeoWikiSection(): void {
		$this->assertAllPagesLinkInNeoWikiSection(
			namespace: NeoWikiExtension::NS_LAYOUT,
			linkId: 't-neowiki-layouts',
			text: 'All layouts',
			hrefContains: 'Layouts',
			hrefExcludes: 'Schemas'
		);
	}

	public function testAllMappingsLinkIsPlacedInTheNeoWikiSection(): void {
		$this->assertAllPagesLinkInNeoWikiSection(
			namespace: NeoWikiExtension::NS_MAPPING,
			linkId: 't-neowiki-mappings',
			text: 'All mappings',
			hrefContains: 'Mappings',
			hrefExcludes: 'Schemas'
		);
	}

	public function testAddsNoAllPagesLinkOutsideNeoWikiNamespaces(): void {
		$sidebar = $this->buildSidebar( Title::makeTitle( NS_MAIN, 'Ordinary Page' ) );

		$this->assertNull( $this->findLinkById( $sidebar[self::NEOWIKI_SECTION] ?? [], 't-neowiki-schemas' ) );
		$this->assertNull( $this->findLinkById( $sidebar[self::NEOWIKI_SECTION] ?? [], 't-neowiki-layouts' ) );
		$this->assertNull( $this->findLinkById( $sidebar[self::NEOWIKI_SECTION] ?? [], 't-neowiki-mappings' ) );
	}

	public function testCreateSubjectLinkIsPlacedInTheNeoWikiSection(): void {
		$link = $this->findLinkById( $this->neoWikiSectionOnAnOrdinaryPage(), 't-neowiki-create-subject-page' );

		$this->assertNotNull( $link, 'Expected the create-subject link in the NeoWiki sidebar section.' );
		$this->assertSame( 'Create subject', $link['text'] );
		$this->assertStringContainsString( 'CreateSubject', $link['href'] );
	}

	public function testCreateSubjectLinkIsListedAfterThePageTools(): void {
		$section = $this->neoWikiSectionOnAnOrdinaryPage();

		$this->assertSame( 't-neowiki-create-subject-page', end( $section )['id'] );
	}

	public function testCreateSubjectLinkIsAbsentForAUserWhoMayNotCreatePages(): void {
		$this->setGroupPermissions( '*', 'createpage', false );
		$this->setGroupPermissions( 'user', 'createpage', false );

		$this->assertNull(
			$this->findLinkById( $this->neoWikiSectionOnAnOrdinaryPage(), 't-neowiki-create-subject-page' )
		);
	}

	public function testCreateSubjectLinkIsAbsentForAUserWhoMayNotEdit(): void {
		$this->setGroupPermissions( '*', 'edit', false );
		$this->setGroupPermissions( 'user', 'edit', false );

		$this->assertNull(
			$this->findLinkById( $this->neoWikiSectionOnAnOrdinaryPage(), 't-neowiki-create-subject-page' )
		);
	}

	public function testCreateSubjectLinkIsAbsentWhereThePageToolsOpenTheSubjectCreator(): void {
		$section = $this->neoWikiSectionOnAPageTheCreatorOpensOn();

		$this->assertNotNull(
			$this->findLinkById( $section, 't-neowiki-create-subject' ),
			'Expected the page tool that opens the Subject creator on the page being viewed.'
		);
		$this->assertNull(
			$this->findLinkById( $section, 't-neowiki-create-subject-page' ),
			'The special page must not be linked where the page tools already open the creator.'
		);
	}

	public function testCreateSubjectLinkIsShownOutsideTheContentNamespaces(): void {
		$this->assertNotNull(
			$this->findLinkById(
				$this->neoWikiSection( Title::makeTitle( NS_HELP, 'Example' ) ),
				't-neowiki-create-subject-page'
			)
		);
	}

	public function testPageToolsAreAbsentOutsideTheContentNamespaces(): void {
		$page = $this->getExistingTestPage( Title::makeTitle( NS_HELP, 'Existing Help Page' ) );

		$this->assertSame(
			[ 't-neowiki-overview', 't-neowiki-create-subject-page' ],
			array_column( $this->neoWikiSection( $page->getTitle(), $page->getLatest() ), 'id' )
		);
	}

	public function testSpecialPagesShowTheLinksThatDescribeNoPage(): void {
		$this->assertSame(
			[ 't-neowiki-overview', 't-neowiki-create-subject-page' ],
			array_column( $this->neoWikiSectionOnASpecialPage(), 'id' )
		);
	}

	/**
	 * Core shows Special:Badtitle for a title the user may not read, so the handler meets it on ordinary requests.
	 */
	public function testOverviewLinkIsShownOnTheBadtitlePage(): void {
		$this->assertNotNull(
			$this->findLinkById(
				$this->neoWikiSection( Title::makeTitle( NS_SPECIAL, 'Badtitle' ) ),
				't-neowiki-overview'
			)
		);
	}

	private function assertAllPagesLinkInNeoWikiSection(
		int $namespace,
		string $linkId,
		string $text,
		string $hrefContains,
		string $hrefExcludes
	): void {
		$sidebar = $this->buildSidebar( Title::makeTitle( $namespace, 'Example' ) );

		$link = $this->findLinkById( $sidebar[self::NEOWIKI_SECTION] ?? [], $linkId );

		$this->assertNotNull( $link, "Expected the $linkId link in the NeoWiki sidebar section." );
		$this->assertSame( $text, $link['text'] );
		$this->assertStringContainsString( $hrefContains, $link['href'] );
		$this->assertStringNotContainsString( $hrefExcludes, $link['href'] );

		$this->assertNull(
			$this->findLinkById( $sidebar['TOOLBOX'] ?? [], $linkId ),
			"The $linkId link must not be in the generic Tools section."
		);
	}

	private function neoWikiSectionOnAnOrdinaryPage(): array {
		return $this->neoWikiSection( Title::makeTitle( NS_MAIN, 'Ordinary Page' ) );
	}

	/**
	 * The page tools offer the creator on a content page that exists and is shown at its latest revision.
	 */
	private function neoWikiSectionOnAPageTheCreatorOpensOn(): array {
		$page = $this->getExistingTestPage( Title::makeTitle( NS_MAIN, 'Existing Page' ) );

		return $this->neoWikiSection( $page->getTitle(), $page->getLatest() );
	}

	private function neoWikiSectionOnASpecialPage(): array {
		return $this->neoWikiSection( Title::makeTitle( NS_SPECIAL, 'Subject' ) );
	}

	private function neoWikiSection( Title $title, ?int $revisionId = null ): array {
		$sidebar = $this->buildSidebar( $title, $this->getTestUser()->getUser(), $revisionId );

		return $sidebar[self::NEOWIKI_SECTION] ?? [];
	}

	private function buildSidebar( Title $title, ?User $user = null, ?int $revisionId = null ): array {
		$context = new RequestContext();
		$context->setTitle( $title );

		if ( $user !== null ) {
			$context->setUser( $user );
		}

		$context->getOutput()->setRevisionId( $revisionId );

		$sidebar = [];
		NeoWikiHooks::onSidebarBeforeOutput( $context->getSkin(), $sidebar );

		return $sidebar;
	}

	private function findLinkById( array $links, string $id ): ?array {
		foreach ( $links as $link ) {
			if ( ( $link['id'] ?? null ) === $id ) {
				return $link;
			}
		}

		return null;
	}

}
