<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onAlternateEdit
 * @group Database
 */
class MappingPageEditNoticeTest extends MediaWikiIntegrationTestCase {

	private function runAlternateEdit( Title $title ): EditPage {
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$context->setTitle( $title );

		$editPage = new EditPage( Article::newFromTitle( $title, $context ) );
		NeoWikiHooks::onAlternateEdit( $editPage );

		return $editPage;
	}

	private function mappingTitle(): Title {
		return Title::makeTitle( NeoWikiExtension::NS_MAPPING, 'EDM' );
	}

	public function testMappingPageEditLinksTheMappingFormatDocumentation(): void {
		$this->assertStringContainsString(
			'href="https://neowiki.ai/docs/authoring/mapping-format"',
			$this->runAlternateEdit( $this->mappingTitle() )->editFormTextTop
		);
	}

	public function testMappingPageEditKeepsTheMediaWikiIntro(): void {
		$this->assertFalse( $this->runAlternateEdit( $this->mappingTitle() )->suppressIntro );
	}

	public static function namespaceWithoutTheNoticeProvider(): iterable {
		yield 'the Mapping talk namespace' => [ NS_NEOWIKI_MAPPING_TALK ];
		yield 'the main namespace' => [ NS_MAIN ];
	}

	/**
	 * @dataProvider namespaceWithoutTheNoticeProvider
	 */
	public function testEditOutsideTheMappingNamespaceCarriesNoNotice( int $namespace ): void {
		$this->assertSame( '', $this->runAlternateEdit( Title::makeTitle( $namespace, 'EDM' ) )->editFormTextTop );
	}

}
