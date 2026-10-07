<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\SubjectLabelHtml;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the database for the page linked to, whose label is read from its Subject slot.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onHtmlPageLinkRendererBegin
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onChangesListInitRows
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onChangesListSpecialPageStructuredFilters
 * @group Database
 */
class SubjectIdTitledPageLinkTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID = 's1zz1111111azz8';
	private const string SUBJECT_NAMESPACE_ID = 's1zz1111111azz9';
	private const string UNLABELLED_ID = 's1zz1111111azz7';

	private int $pageId;

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );

		$this->pageId = $this->createPageWithSubjects(
			self::SUBJECT_ID,
			TestSubject::build( id: self::SUBJECT_ID, label: 'Ada Lovelace' )
		)->getPageId();
	}

	public function testAChangeListLinksToThePageByItsLabelAndId(): void {
		$link = $this->linkFromChangeList();

		$this->assertStringContainsString( 'Ada Lovelace', $link );
		$this->assertStringContainsString( self::SUBJECT_ID, $link );
	}

	public function testAChangeListLinksToThePageOfASubjectWithoutALabelByItsTitle(): void {
		$pageId = $this->createPageWithSubjects(
			ucfirst( self::UNLABELLED_ID ),
			TestSubject::build( id: self::UNLABELLED_ID, label: null )
		)->getPageId();
		$this->listRow( $pageId, NS_MAIN, ucfirst( self::UNLABELLED_ID ), null );

		$link = $this->getServiceContainer()->getLinkRenderer()->makeLink( Title::newFromText( self::UNLABELLED_ID ) );

		$this->assertStringContainsString( '>' . ucfirst( self::UNLABELLED_ID ) . '</a>', $link );
	}

	public function testALinkOutsideAChangeListShowsTheTitle(): void {
		$this->assertStringNotContainsString( 'Ada Lovelace', $this->link() );
	}

	/**
	 * The parser links with a link renderer of its own, and the parser cache would keep a label after
	 * it changed.
	 */
	public function testALinkRenderedAsPageContentShowsTheTitle(): void {
		$this->listPage( NS_MAIN );

		$link = $this->getServiceContainer()->getLinkRendererFactory()->create()->makeLink( $this->pageTitle() );

		$this->assertStringNotContainsString( 'Ada Lovelace', $link );
	}

	public function testALinkWithTextOfItsOwnKeepsIt(): void {
		$this->listPage( NS_MAIN );

		$link = $this->getServiceContainer()->getLinkRenderer()->makeLink( $this->pageTitle(), 'Custom text' );

		$this->assertStringNotContainsString( 'Ada Lovelace', $link );
	}

	/**
	 * The label is the page's content, so it goes only to a reader who may read the page.
	 */
	public function testAReaderWhoMayNotReadThePageSeesTheTitle(): void {
		$this->setGroupPermissions( '*', 'read', false );
		RequestContext::getMain()->setUser( $this->getServiceContainer()->getUserFactory()->newAnonymous() );

		$this->assertStringNotContainsString( 'Ada Lovelace', $this->linkFromChangeList() );
	}

	public function testAChangeListLinksToAMainNamespacePageByItsLabelAndIdOnASubjectFirstWiki(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );

		$this->assertStringContainsString( 'Ada Lovelace', $this->linkFromChangeList() );
	}

	/**
	 * A list transcluded into a cached page, such as the site notice, renders later in the same request.
	 */
	public function testALinkRenderedAfterTheListInAnotherOutputShowsTheTitle(): void {
		$this->listPage( NS_MAIN );
		RequestContext::getMain()->setOutput( new OutputPage( RequestContext::getMain() ) );

		$this->assertStringNotContainsString( 'Ada Lovelace', $this->link() );
	}

	/**
	 * A page transcluding the list would keep the label in its cache after it changed.
	 */
	public function testAChangeListTranscludedIntoAPageShowsTheTitle(): void {
		$this->listPage( NS_MAIN, new DerivativeContext( new RequestContext() ) );

		$this->assertStringNotContainsString( 'Ada Lovelace', $this->link() );
	}

	/**
	 * Recent changes as a reader's request runs it, through the hooks MediaWiki calls.
	 */
	public function testRecentChangesLinksToThePageByItsLabelAndId(): void {
		$html = $this->renderRecentChanges()->getHTML();

		$this->assertStringContainsString( 'Ada Lovelace', $html );
		$this->assertStringContainsString( self::SUBJECT_ID, $html );
	}

	public function testRecentChangesLinksToAPageInTheSubjectNamespaceByItsLabelAndIdOnASubjectFirstWiki(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$this->createSubjectNamespacePage();

		$html = $this->renderRecentChanges()->getHTML();

		$this->assertStringContainsString( 'Grace Hopper', $html );
		$this->assertStringContainsString( self::SUBJECT_NAMESPACE_ID, $html );
	}

	/**
	 * The filters reload the list without its styles, and it can start out empty.
	 */
	public function testRecentChangesCarriesTheLabelStyles(): void {
		$this->assertContains( SubjectLabelHtml::STYLE_MODULE, $this->renderRecentChanges()->getModuleStyles() );
	}

	private function renderRecentChanges(): OutputPage {
		// The page's Recent changes row is written after the edit.
		DeferredUpdates::doUpdates();
		RequestContext::getMain()->setTitle( SpecialPage::getTitleFor( 'Recentchanges' ) );

		$recentChanges = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Recentchanges' );
		$recentChanges->setContext( RequestContext::getMain() );
		$recentChanges->execute( '' );

		return RequestContext::getMain()->getOutput();
	}

	private function linkFromChangeList(): string {
		$this->listPage( NS_MAIN );

		return $this->link();
	}

	/**
	 * What a change list does with its rows before rendering them. It renders in the request's own
	 * context unless it is transcluded into a page.
	 */
	private function listPage( int $rowNamespace, ?DerivativeContext $listContext = null ): void {
		$this->listRow( $this->pageId, $rowNamespace, ucfirst( self::SUBJECT_ID ), $listContext );
	}

	private function listRow( int $pageId, int $namespace, string $title, ?DerivativeContext $listContext ): void {
		NeoWikiHooks::onChangesListInitRows(
			$listContext ?? new DerivativeContext( RequestContext::getMain() ),
			[ (object)[ 'rc_cur_id' => $pageId, 'rc_namespace' => $namespace, 'rc_title' => $title ] ]
		);
	}

	private function link(): string {
		return $this->getServiceContainer()->getLinkRenderer()->makeLink( $this->pageTitle() );
	}

	private function pageTitle(): Title {
		return Title::newFromText( self::SUBJECT_ID );
	}

	private function createSubjectNamespacePage(): int {
		return $this->createPageWithSubjects(
			$this->subjectNamespacePageTitle()->getPrefixedText(),
			TestSubject::build( id: self::SUBJECT_NAMESPACE_ID, label: 'Grace Hopper' )
		)->getPageId();
	}

	private function subjectNamespacePageTitle(): Title {
		return Title::makeTitle( NeoWikiExtension::NS_SUBJECT, ucfirst( self::SUBJECT_NAMESPACE_ID ) );
	}

}
