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

	private function editFormTop( Title $title ): string {
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$context->setTitle( $title );

		$editPage = new EditPage( Article::newFromTitle( $title, $context ) );
		NeoWikiHooks::onAlternateEdit( $editPage );

		return $editPage->editFormTextTop;
	}

	public function testMappingPageEditLinksTheMappingFormatDocumentation(): void {
		$this->assertStringContainsString(
			'href="https://neowiki.ai/docs/authoring/mapping-format"',
			$this->editFormTop( Title::makeTitle( NeoWikiExtension::NS_MAPPING, 'EDM' ) )
		);
	}

	public function testEditOutsideTheMappingNamespaceCarriesNoNotice(): void {
		$this->assertSame( '', $this->editFormTop( Title::makeTitle( NS_MAIN, 'EDM' ) ) );
	}

}
