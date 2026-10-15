<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Article;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the database for the pages whose Subjects the view reads and the revisions a diff compares.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBeforePageDisplay
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onDifferenceEngineRenderRevisionAddParserOutput
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onParserAfterTidy
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onOutputPageParserOutput
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\MainSubjectParserFunction
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::shouldAutoRenderMainSubject
 * @group Database
 */
class AutoRenderMainSubjectTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID = 's1zz1111111azz1';
	private const string OTHER_SUBJECT_ID = 's1zz1111111azz2';

	private const string PAGE_NAME = 'Auto render page';
	private const string PAGE_TEXT = 'Text of the page';

	private const string IN_FULL = 'id="ext-neowiki-main-subject"';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	/**
	 * @dataProvider wikiModeProvider
	 */
	public function testDoesNotAutoRenderMainSubjectWhenConfigDisabled( bool $subjectFirst ): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', $subjectFirst );
		$this->createPageWithMainSubjectAndText();
		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$html = $this->renderRequest( [] );

		$this->assertStringNotContainsString( self::viewOf( self::SUBJECT_ID ), $html );
	}

	public static function wikiModeProvider(): iterable {
		yield 'page-first' => [ false ];
		yield 'subject-first' => [ true ];
	}

	public function testAutoRendersMainSubjectAtTheTopOfThePageByDefault(): void {
		$this->createPageWithMainSubjectAndText();

		$html = $this->renderRequest( [] );

		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), self::htmlAboveThePageText( $html ) );
	}

	public function testRendersAppContainerWhenAutoRenderDisabled(): void {
		$this->createPageWithMainSubjectAndText();
		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$html = $this->renderRequest( [] );

		$this->assertStringContainsString( 'id="ext-neowiki-app"', $html );
	}

	public function testNoMainSubjectIsShownAboveADiff(): void {
		$html = $this->renderDiffOfASubjectEdit();

		$this->assertStringNotContainsString( self::viewOf( self::SUBJECT_ID ), self::htmlAboveTheDiff( $html ) );
	}

	public function testADiffShowsTheMainSubjectAtTheTopOfTheRevisionBelowIt(): void {
		$html = $this->renderDiffOfASubjectEdit();

		$this->assertStringContainsString(
			self::viewOf( self::SUBJECT_ID ),
			self::htmlFromTheRevisionHeadingToThePageText( $html )
		);
	}

	public function testADiffOfAnOldRevisionShowsTheMainSubjectItHeld(): void {
		$oldRevision = $this->createPageWithSubjects( self::PAGE_NAME, TestSubject::build( id: self::SUBJECT_ID ) );
		$this->changeSubjectsOfPage( self::PAGE_NAME, TestSubject::build( id: self::OTHER_SUBJECT_ID ) );

		$html = $this->renderRequest( [ 'diff' => 'prev', 'oldid' => (string)$oldRevision->getId() ] );

		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), $html );
	}

	public function testDoesNotAutoRenderMainSubjectOnADiffWhenConfigDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$html = $this->renderDiffOfASubjectEdit();

		$this->assertStringNotContainsString( self::viewOf( self::SUBJECT_ID ), $html );
	}

	public function testRendersAppContainerOnADiff(): void {
		$html = $this->renderDiffOfASubjectEdit();

		$this->assertStringContainsString( 'id="ext-neowiki-app"', $html );
	}

	public function testAPageFirstWikiShowsTheMainSubjectOfAPageWithoutTextAsAnInfobox(): void {
		$this->createPageWithText( '' );

		$html = $this->renderRequest( [] );

		$this->assertTrue( $this->showsAnInfobox( $html ) );
		$this->assertStringNotContainsString( self::IN_FULL, $html );
	}

	/**
	 * @dataProvider pageTextProvider
	 */
	public function testASubjectFirstWikiShowsTheMainSubjectInFullBelowThePageText( string $text ): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$this->createPageWithText( $text );

		$html = $this->renderRequest( [] );
		$inFull = $this->inFullElement( $html );

		$this->assertAppearsInOrder( $html, $text, self::IN_FULL );
		// The frontend mounts the Subject UI on this element, and the focus styles apply under its class.
		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), $inFull );
		$this->assertStringContainsString( 'ext-neowiki-ui', $inFull );
		$this->assertFalse( $this->showsAnInfobox( $html ) );
	}

	public static function pageTextProvider(): iterable {
		yield 'with text' => [ self::PAGE_TEXT ];
		yield 'without text' => [ '' ];
	}

	public function testASubjectFirstWikiGivesTheSubjectUiItsConfiguration(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$this->createPageWithMainSubjectAndText();

		$this->assertArrayHasKey( 'wgNeoWikiSubjectIriBase', $this->viewPage( [] )->getJsConfigVars() );
	}

	/**
	 * @dataProvider wikiModeProvider
	 */
	public function testAnOldRevisionShowsTheMainSubjectItHeldAsAnInfobox( bool $subjectFirst ): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', $subjectFirst );
		$oldRevision = $this->createPageWithText( self::PAGE_TEXT );
		$this->changeSubjectsOfPage( self::PAGE_NAME, TestSubject::build( id: self::OTHER_SUBJECT_ID ) );

		$html = $this->renderRequest( [ 'oldid' => (string)$oldRevision->getId() ] );

		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), $html );
		$this->assertTrue( $this->showsAnInfobox( $html ) );
		$this->assertStringNotContainsString( self::IN_FULL, $html );
	}

	public function testADiffOnASubjectFirstWikiShowsTheMainSubjectAsAnInfobox(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );

		$html = $this->renderDiffOfASubjectEdit();

		$this->assertTrue( $this->showsAnInfobox( $html ) );
		$this->assertStringNotContainsString( self::IN_FULL, $html );
	}

	/**
	 * @dataProvider wikiModeProvider
	 */
	public function testAPagePlacingItsMainSubjectShowsItInFullThereAndNowhereElse( bool $subjectFirst ): void {
		$this->skipWhereParsoidParses();
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', $subjectFirst );
		$this->createPageWithText( "Before it.\n\n{{#main_subject: }}\n\nAfter it." );

		$html = $this->renderRequest( [] );

		$this->assertSame( 1, substr_count( $html, self::viewOf( self::SUBJECT_ID ) ) );
		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), $this->inFullElement( $html ) );
		$this->assertAppearsInOrder( $html, 'Before it.', self::IN_FULL, 'After it.' );
	}

	public function testAnOldRevisionOfAPagePlacingItsMainSubjectShowsItOnlyWhereItIsPlaced(): void {
		$this->skipWhereParsoidParses();
		$oldRevision = $this->createPageWithText( '{{#main_subject: }}' );
		$this->changeSubjectsOfPage( self::PAGE_NAME, TestSubject::build( id: self::SUBJECT_ID, label: 'Edited' ) );

		$html = $this->renderRequest( [ 'oldid' => (string)$oldRevision->getId() ] );

		$this->assertSame( 1, substr_count( $html, self::viewOf( self::SUBJECT_ID ) ) );
		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), $this->inFullElement( $html ) );
	}

	public function testAnOldRevisionPlacesTheMainSubjectItHad(): void {
		$oldRevision = $this->createPageWithSubjects(
			self::PAGE_NAME,
			mainSubject: TestSubject::build( id: self::SUBJECT_ID ),
			otherSubjects: new SubjectMap( TestSubject::build( id: self::OTHER_SUBJECT_ID ) ),
			mainContent: new WikitextContent( '{{#main_subject: }}' )
		);
		$this->changeSubjectsOfPage(
			self::PAGE_NAME,
			mainSubject: TestSubject::build( id: self::OTHER_SUBJECT_ID ),
			otherSubjects: new SubjectMap( TestSubject::build( id: self::SUBJECT_ID ) )
		);

		$html = $this->renderRequest( [ 'oldid' => (string)$oldRevision->getId() ] );

		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), $this->inFullElement( $html ) );
	}

	/**
	 * A preview parses the edited text as a revision of its own, without the page's Subjects. Only a parse
	 * through the content handler builds that revision, as an edit preview's does.
	 */
	public function testAPreviewPlacesThePagesMainSubject(): void {
		$this->createPageWithText( self::PAGE_TEXT );
		$options = ParserOptions::newFromAnon();
		$options->setIsPreview( true );

		$html = $this->getServiceContainer()->getContentRenderer()
			->getParserOutput( new WikitextContent( '{{#main_subject: }}' ), Title::newFromText( self::PAGE_NAME ), null, $options )
			->runOutputPipeline( $options, [] )->getContentHolderText();

		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), $html );
	}

	/**
	 * As when the API or Special:ExpandTemplates parses text for a title.
	 */
	public function testAParseOfNoRevisionPlacesThePagesMainSubject(): void {
		$this->createPageWithText( self::PAGE_TEXT );

		$this->assertStringContainsString(
			self::viewOf( self::SUBJECT_ID ),
			$this->parseWikitextOn( self::PAGE_NAME, '{{#main_subject: }}' )
		);
	}

	public function testADiffOfAPagePlacingItsMainSubjectShowsItOnlyWhereItIsPlaced(): void {
		$this->skipWhereParsoidParses();
		$this->createPageWithText( '{{#main_subject: }}' );
		$edit = $this->changeSubjectsOfPage( self::PAGE_NAME, TestSubject::build( id: self::SUBJECT_ID, label: 'Edited' ) );

		$html = $this->renderRequest( [ 'diff' => 'prev', 'oldid' => (string)$edit->getId() ] );

		$this->assertSame( 1, substr_count( $html, self::viewOf( self::SUBJECT_ID ) ) );
		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), $this->inFullElement( $html ) );
	}

	public function testAPlacementThatDoesNotReachThePageLeavesAutomaticRenderingOn(): void {
		// PLURAL expands every form and keeps one.
		$this->createPageWithText( self::PAGE_TEXT . '{{PLURAL:1||{{#main_subject: }}}}' );

		$this->assertTrue( $this->showsAnInfobox( $this->renderRequest( [] ) ) );
	}

	public function testAPlacementAfterADiscardedOneShowsTheMainSubjectInFull(): void {
		$this->skipWhereParsoidParses();
		$this->createPageWithText( self::PAGE_TEXT . '{{PLURAL:2|{{#main_subject: }}|{{#main_subject: }}}}' );

		$html = $this->renderRequest( [] );

		$this->assertStringContainsString( self::IN_FULL, $html );
		$this->assertFalse( $this->showsAnInfobox( $html ) );
	}

	public function testAPagePlacingItsMainSubjectGivesTheSubjectUiItsConfigurationWithAutomaticRenderingOff(): void {
		$this->skipWhereParsoidParses();
		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );
		$this->createPageWithText( '{{#main_subject: }}' );

		$this->assertArrayHasKey( 'wgNeoWikiSubjectIriBase', $this->viewPage( [] )->getJsConfigVars() );
	}

	private function skipWhereParsoidParses(): void {
		$config = $this->getServiceContainer()->getMainConfig();

		if ( $config->has( 'UseParsoidParser' ) && $config->get( 'UseParsoidParser' ) ) {
			$this->markTestSkipped(
				'Placing the Main Subject needs the legacy parser: https://github.com/ProfessionalWiki/NeoWiki/issues/1689'
			);
		}
	}

	private function createPageWithMainSubjectAndText(): void {
		$this->createPageWithText( self::PAGE_TEXT );
	}

	private function createPageWithText( string $text ): ?RevisionRecord {
		return $this->createPageWithSubjects(
			self::PAGE_NAME,
			TestSubject::build( id: self::SUBJECT_ID ),
			mainContent: new WikitextContent( $text )
		);
	}

	private function renderDiffOfASubjectEdit(): string {
		$this->createPageWithMainSubjectAndText();

		$edit = $this->changeSubjectsOfPage(
			self::PAGE_NAME,
			TestSubject::build( id: self::SUBJECT_ID, label: 'Edited label' )
		);

		return $this->renderRequest( [ 'diff' => 'prev', 'oldid' => (string)$edit->getId() ] );
	}

	/**
	 * @param array<string, string> $query
	 */
	private function renderRequest( array $query ): string {
		return $this->viewPage( $query )->getHTML();
	}

	/**
	 * @param array<string, string> $query
	 */
	private function viewPage( array $query ): OutputPage {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( $query ) );
		$context->setTitle( Title::newFromText( self::PAGE_NAME ) );

		Article::newFromTitle( $context->getTitle(), $context )->view();
		NeoWikiHooks::onBeforePageDisplay( $context->getOutput(), $context->getSkin() );

		return $context->getOutput();
	}

	private static function viewOf( string $subjectId ): string {
		return 'data-mw-neowiki-subject-id="' . $subjectId . '"';
	}

	private static function htmlAboveTheDiff( string $html ): string {
		return strstr( $html, '<table class="diff', true );
	}

	private static function htmlAboveThePageText( string $html ): string {
		return strstr( $html, self::PAGE_TEXT, true );
	}

	private static function htmlFromTheRevisionHeadingToThePageText( string $html ): string {
		return self::htmlAboveThePageText( strstr( $html, 'diff-currentversion-title' ) );
	}

	/**
	 * The opening tag of the element the Subject UI is mounted on, or an empty string without one.
	 */
	private function inFullElement( string $html ): string {
		return preg_match( '/<\w+[^>]*' . self::IN_FULL . '[^>]*>/', $html, $matches ) === 1 ? $matches[0] : '';
	}

	private function showsAnInfobox( string $html ): bool {
		return preg_match( '/class="[^"]*\bext-neowiki-view\b/', $html ) === 1;
	}

	private function assertAppearsInOrder( string $html, string ...$parts ): void {
		$this->assertMatchesRegularExpression(
			'/' . implode( '.*', array_map( static fn ( string $part ) => preg_quote( $part, '/' ), $parts ) ) . '/s',
			$html
		);
	}

}
