<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * The database holds the page, whose rights decide who may edit it.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBeforePageDisplay
 * @group Database
 */
class ContentPageFrontendLoadingTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	private const string SUBJECT_ID = 's1demo1aaaaaaa1';

	public function testLoadsNoFrontendForAReaderOfAPageWithoutNeoWikiUi(): void {
		$out = $this->displayToAReader( '<p>Paris is the capital of France.</p>' );

		$this->assertNotContains( 'ext.neowiki', $out->getModules() );
		$this->assertNotContains( 'ext.neowiki.styles', $out->getModuleStyles() );
	}

	public function testLoadsTheFrontendForAReaderOfAPageShowingAView(): void {
		$out = $this->displayToAReader( '<p>Paris</p>' . ViewHtmlBuilder::viewPlaceholderHtml( self::SUBJECT_ID ) );

		$this->assertContains( 'ext.neowiki', $out->getModules() );
	}

	public function testLoadsTheFrontendForAReaderOfAPagePlacingItsMainSubject(): void {
		$out = $this->displayToAReader( ViewHtmlBuilder::mainSubjectInFullPlaceholderHtml( self::SUBJECT_ID ) );

		$this->assertContains( 'ext.neowiki', $out->getModules() );
	}

	public function testLoadsTheFrontendForAViewerWhoMayEditThePage(): void {
		$out = $this->display( '<p>Paris is the capital of France.</p>', $this->getTestSysop()->getAuthority() );

		$this->assertContains( 'ext.neowiki', $out->getModules() );
		$this->assertStringContainsString( 'id="ext-neowiki-app"', $out->getHTML() );
	}

	private function displayToAReader( string $html ): OutputPage {
		return $this->display( $html, $this->authorityThatCannotEditPageId( $this->title()->getArticleID() ) );
	}

	private function display( string $html, Authority $authority ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( $this->title() );
		$context->setAuthority( $authority );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $this->title()->getLatestRevID() );
		$out->addHTML( $html );

		NeoWikiHooks::onBeforePageDisplay( $out, $context->getSkin() );

		return $out;
	}

	private function title(): Title {
		return $this->getExistingTestPage( 'ContentPageFrontendLoadingTest' )->getTitle();
	}

}
