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
class MappingPageEditDocsPointerTest extends MediaWikiIntegrationTestCase {

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
		$editPage = $this->runAlternateEdit( $this->mappingTitle() );

		$this->assertStringContainsString(
			'href="https://neowiki.ai/docs/authoring/mapping-format"',
			$editPage->editFormTextTop
		);
		$this->assertFalse( $editPage->suppressIntro, 'Core\'s edit intro must survive, unlike on the config page.' );
	}

	public static function namespaceWithoutThePointerProvider(): iterable {
		yield 'the Mapping talk namespace' => [ NS_NEOWIKI_MAPPING_TALK ];
		yield 'the Schema namespace' => [ NeoWikiExtension::NS_SCHEMA ];
		yield 'the main namespace' => [ NS_MAIN ];
	}

	/**
	 * @dataProvider namespaceWithoutThePointerProvider
	 */
	public function testEditOutsideTheMappingNamespaceCarriesNoPointer( int $namespace ): void {
		$this->assertSame( '', $this->runAlternateEdit( Title::makeTitle( $namespace, 'EDM' ) )->editFormTextTop );
	}

}
