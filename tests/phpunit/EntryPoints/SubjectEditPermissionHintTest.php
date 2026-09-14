<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBeforePageDisplay
 * @group Database
 */
class SubjectEditPermissionHintTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
	}

	public function testTellsTheFrontendTheViewerMayEditThePagesSubjects(): void {
		$title = $this->getExistingTestPage( 'SubjectEditPermissionHintTest editable' )->getTitle();

		$out = $this->renderContentPage( $title, $this->getTestSysop()->getAuthority() );

		$this->assertTrue( $out->getJsConfigVars()['wgNeoWikiCanEditPageSubjects'] );
	}

	public function testTellsTheFrontendAViewerWhoMayNotEditThePageMayNotEditItsSubjects(): void {
		$title = $this->getExistingTestPage( 'SubjectEditPermissionHintTest protected' )->getTitle();

		$out = $this->renderContentPage(
			$title,
			$this->authorityThatCannotEditPageId( $title->getArticleID() )
		);

		$this->assertFalse(
			$out->getJsConfigVars()['wgNeoWikiCanEditPageSubjects'],
			'The decision must be the one made for the page being viewed.'
		);
	}

	public function testOffersTheSubjectCreatorOnlyToAViewerWhoMayEditThePage(): void {
		$title = $this->getExistingTestPage( 'SubjectEditPermissionHintTest creator' )->getTitle();

		$editable = $this->renderContentPage( $title, $this->getTestSysop()->getAuthority() );
		$protected = $this->renderContentPage(
			$title,
			$this->authorityThatCannotEditPageId( $title->getArticleID() )
		);

		$this->assertStringContainsString( 'data-mw-neowiki-create-subject', $editable->getHTML() );
		$this->assertStringNotContainsString( 'data-mw-neowiki-create-subject', $protected->getHTML() );
	}

	private function renderContentPage( Title $title, Authority $authority ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setAuthority( $authority );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $title->getLatestRevID() );

		NeoWikiHooks::onBeforePageDisplay( $out, $context->getSkin() );

		return $out;
	}

}
