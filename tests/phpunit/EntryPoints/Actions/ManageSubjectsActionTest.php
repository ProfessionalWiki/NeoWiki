<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Actions;

use Action;
use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\ManageSubjectsAction;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Actions\ManageSubjectsAction
 * @group Database
 */
class ManageSubjectsActionTest extends MediaWikiIntegrationTestCase {

	public function testRendersMountDivOnContentPage(): void {
		$title = $this->insertContentPage( 'ManageSubjectsActionTest_Page' );

		$html = $this->executeAction( $title );

		$this->assertStringContainsString( 'id="ext-neowiki-manage-subjects"', $html );
	}

	public function testRendersErrorBoxOnNonExistentPage(): void {
		$title = Title::newFromText( 'ManageSubjectsActionTest_DoesNotExist' );

		$html = $this->executeAction( $title );

		$this->assertStringNotContainsString( 'id="ext-neowiki-manage-subjects"', $html );
		$this->assertStringContainsString( 'cdx-message--error', $html );
		$this->assertStringContainsString( 'Subject management is not available', $html );
	}

	public function testIsEligibleForContentNamespacePages(): void {
		$title = $this->insertContentPage( 'ManageSubjectsActionTest_Eligible' );

		$this->assertTrue( ManageSubjectsAction::isEligibleTitle( $title ) );
	}

	public function testIsNotEligibleForNonExistentTitle(): void {
		$this->assertFalse(
			ManageSubjectsAction::isEligibleTitle( Title::newFromText( 'ManageSubjectsActionTest_NotCreated' ) )
		);
	}

	private function insertContentPage( string $name ): Title {
		$title = Title::newFromText( $name );
		$this->editPage( $title, 'page content' );
		return Title::newFromID( $title->getArticleID() );
	}

	private function executeAction( Title $title ): string {
		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setRequest( new FauxRequest( [ 'action' => 'managesubjects' ] ) );
		$context->setUser( $this->getTestUser()->getUser() );

		$article = Article::newFromTitle( $title, $context );
		$action = Action::factory( 'managesubjects', $article, $context );

		$context->getOutput()->setTitle( $title );
		$action->show();

		return $context->getOutput()->getHTML();
	}

}
