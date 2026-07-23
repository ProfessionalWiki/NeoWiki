<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onSidebarBeforeOutput
 * @group Database
 */
class NeoWikiSidebarLinkTest extends NeoWikiIntegrationTestCase {

	private const NEOWIKI_SECTION = 'neowiki-page-tools-label';

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

	private function buildSidebar( Title $title ): array {
		$context = new RequestContext();
		$context->setTitle( $title );

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
