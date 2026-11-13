<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Language\RawMessage;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\SubjectLabelHtml;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the database for the page linked to, whose label is read from the published Subject index.
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

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );

		$this->createPageWithSubjects(
			self::SUBJECT_ID,
			TestSubject::build( id: self::SUBJECT_ID, label: 'Ada Lovelace' )
		);
	}

	public function testALinkToThePageReadsAsItsLabelAndId(): void {
		$link = $this->link();

		$this->assertStringContainsString( 'Ada Lovelace', $link );
		$this->assertStringContainsString( self::SUBJECT_ID, $link );
	}

	/**
	 * Edit summaries, page histories and Contributions pass the title as the link's text.
	 */
	public function testAnEditSummaryLinksToThePageByItsLabelAndId(): void {
		$summary = $this->getServiceContainer()->getCommentFormatter()->format( 'See [[' . self::SUBJECT_ID . ']]' );

		$this->assertStringContainsString( 'Ada Lovelace', $summary );
	}

	public function testAnEditSummaryLinkingASectionOfThePageKeepsItsText(): void {
		$summary = $this->getServiceContainer()->getCommentFormatter()->format( 'See [[' . self::SUBJECT_ID . '#Early life]]' );

		$this->assertStringContainsString( self::SUBJECT_ID . '#Early life', $summary );
	}

	/**
	 * All pages and Special:PrefixIndex list a namespace's pages without its prefix.
	 */
	public function testALinkShowingTheTitleWithoutItsNamespaceReadsAsTheLabelAndId(): void {
		$this->createSubjectNamespacePage();

		$link = $this->getServiceContainer()->getLinkRenderer()->makeLink(
			$this->subjectNamespacePageTitle(),
			$this->subjectNamespacePageTitle()->getText()
		);

		$this->assertStringContainsString( 'Grace Hopper', $link );
	}

	public function testALinkWithTextOfItsOwnKeepsIt(): void {
		$link = $this->getServiceContainer()->getLinkRenderer()->makeLink( $this->pageTitle(), 'Custom text' );

		$this->assertStringNotContainsString( 'Ada Lovelace', $link );
	}

	/**
	 * Core's link renderer takes a message as link text, though its documentation names only strings and
	 * HtmlArmor; BlueSpice passes one.
	 */
	public function testALinkWithAMessageAsItsTextKeepsIt(): void {
		$link = $this->getServiceContainer()->getLinkRenderer()->makeLink( $this->pageTitle(), new RawMessage( 'History' ) );

		$this->assertStringContainsString( '>History</a>', $link );
	}

	public function testALinkToThePageOfASubjectWithoutALabelShowsTheTitle(): void {
		$this->createPageWithSubjects(
			ucfirst( self::UNLABELLED_ID ),
			TestSubject::build( id: self::UNLABELLED_ID, label: null )
		);

		$link = $this->getServiceContainer()->getLinkRenderer()->makeLink( Title::newFromText( self::UNLABELLED_ID ) );

		$this->assertStringContainsString( '>' . ucfirst( self::UNLABELLED_ID ) . '</a>', $link );
	}

	/**
	 * The label is the page's content, so it goes only to a reader who may read the page.
	 */
	public function testAReaderWhoMayNotReadThePageSeesTheTitle(): void {
		$this->setGroupPermissions( '*', 'read', false );
		RequestContext::getMain()->setUser( $this->getServiceContainer()->getUserFactory()->newAnonymous() );

		$this->assertStringNotContainsString( 'Ada Lovelace', $this->link() );
	}

	public function testALinkToAMainNamespacePageReadsAsItsLabelAndIdOnASubjectFirstWiki(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );

		$this->assertStringContainsString( 'Ada Lovelace', $this->link() );
	}

	public function testALabelledLinkBringsTheLabelStylesToThePage(): void {
		$this->link();

		$this->assertContains( SubjectLabelHtml::STYLE_MODULE, RequestContext::getMain()->getOutput()->getModuleStyles() );
	}

	/**
	 * The parser cache keeps what a parse renders after the label changes, and serves it to readers who
	 * may not read the page linked to.
	 */
	public function testALinkInPageContentShowsTheTitle(): void {
		$html = $this->parseWikitextOn( 'Linking page', '[[' . self::SUBJECT_ID . ']]' );

		$this->assertStringContainsString( ucfirst( self::SUBJECT_ID ), $html );
		$this->assertStringNotContainsString( 'Ada Lovelace', $html );
	}

	public function testRecentChangesTranscludedIntoAPageShowsTheTitle(): void {
		DeferredUpdates::doUpdates();

		$html = $this->parseWikitextOn( 'Linking page', '{{Special:RecentChanges}}' );

		$this->assertStringContainsString( ucfirst( self::SUBJECT_ID ), $html );
		$this->assertStringNotContainsString( 'Ada Lovelace', $html );
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
	 * The filters reload the list without its styles, so a list that starts out without labels needs them too.
	 */
	public function testRecentChangesWithoutLabelledLinksCarriesTheLabelStyles(): void {
		RequestContext::getMain()->setRequest( new FauxRequest( [ 'namespace' => (string)NS_TALK ] ) );

		$out = $this->renderRecentChanges();

		$this->assertStringNotContainsString( 'Ada Lovelace', $out->getHTML() );
		$this->assertContains( SubjectLabelHtml::STYLE_MODULE, $out->getModuleStyles() );
	}

	public function testAllPagesLinksToThePageByItsLabelAndId(): void {
		$html = $this->renderSpecialPage( 'Allpages' )->getHTML();

		$this->assertStringContainsString( 'Ada Lovelace', $html );
		$this->assertStringContainsString( self::SUBJECT_ID, $html );
	}

	private function renderRecentChanges(): OutputPage {
		// The page's Recent changes row is written after the edit.
		DeferredUpdates::doUpdates();

		return $this->renderSpecialPage( 'Recentchanges' );
	}

	private function renderSpecialPage( string $name ): OutputPage {
		RequestContext::getMain()->setTitle( SpecialPage::getTitleFor( $name ) );

		$specialPage = $this->getServiceContainer()->getSpecialPageFactory()->getPage( $name );
		$specialPage->setContext( RequestContext::getMain() );
		$specialPage->execute( '' );

		return RequestContext::getMain()->getOutput();
	}

	private function link(): string {
		return $this->getServiceContainer()->getLinkRenderer()->makeLink( $this->pageTitle() );
	}

	private function pageTitle(): Title {
		return Title::newFromText( self::SUBJECT_ID );
	}

	private function createSubjectNamespacePage(): void {
		$this->createPageWithSubjects(
			$this->subjectNamespacePageTitle()->getPrefixedText(),
			TestSubject::build( id: self::SUBJECT_NAMESPACE_ID, label: 'Grace Hopper' )
		);
	}

	private function subjectNamespacePageTitle(): Title {
		return Title::makeTitle( NeoWikiExtension::NS_SUBJECT, ucfirst( self::SUBJECT_NAMESPACE_ID ) );
	}

}
