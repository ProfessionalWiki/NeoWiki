<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Article;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the database for the pages whose Subjects the view reads and the revisions a diff compares.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBeforePageDisplay
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onDifferenceEngineRenderRevisionAddParserOutput
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::shouldAutoRenderMainSubject
 * @group Database
 */
class AutoRenderMainSubjectTest extends NeoWikiIntegrationTestCase {

	private const SUBJECT_ID = 's1zz1111111azz1';
	private const string OTHER_SUBJECT_ID = 's1zz1111111azz2';

	private const string DIFFED_PAGE = 'Auto render diff';
	private const string PAGE_TEXT = 'Text of the diffed page';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testDoesNotAutoRenderMainSubjectWhenConfigDisabled(): void {
		$revision = $this->createPageWithSubjects(
			'Auto render disabled',
			TestSubject::build( id: self::SUBJECT_ID )
		);

		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$html = $this->renderContentPage( 'Auto render disabled', $revision->getId() );

		$this->assertStringNotContainsString( self::SUBJECT_ID, $html );
	}

	public function testAutoRendersMainSubjectByDefault(): void {
		$revision = $this->createPageWithSubjects(
			'Auto render default',
			TestSubject::build( id: self::SUBJECT_ID )
		);

		$html = $this->renderContentPage( 'Auto render default', $revision->getId() );

		$this->assertStringContainsString( self::SUBJECT_ID, $html );
	}

	public function testRendersAppContainerWhenAutoRenderDisabled(): void {
		$revision = $this->createPageWithSubjects(
			'Auto render container',
			TestSubject::build( id: self::SUBJECT_ID )
		);

		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$html = $this->renderContentPage( 'Auto render container', $revision->getId() );

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
		$oldRevision = $this->createPageWithSubjects( self::DIFFED_PAGE, TestSubject::build( id: self::SUBJECT_ID ) );
		$this->changeSubjectsOfPage( self::DIFFED_PAGE, TestSubject::build( id: self::OTHER_SUBJECT_ID ) );

		$html = $this->renderDiff( [ 'diff' => 'prev', 'oldid' => (string)$oldRevision->getId() ] );

		$this->assertStringContainsString( self::viewOf( self::SUBJECT_ID ), $html );
	}

	public function testDoesNotAutoRenderMainSubjectOnADiffWhenConfigDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$html = $this->renderDiffOfASubjectEdit();

		$this->assertStringNotContainsString( self::viewOf( self::SUBJECT_ID ), $html );
	}

	private function renderContentPage( string $pageName, int $revisionId ): string {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( $pageName ) );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $revisionId );

		NeoWikiHooks::onBeforePageDisplay( $out, $context->getSkin() );

		return $out->getHTML();
	}

	/**
	 * The edit leaves the page's text alone, so the text shows only in the revision below the diff.
	 */
	private function renderDiffOfASubjectEdit(): string {
		$this->createPageWithSubjects(
			self::DIFFED_PAGE,
			TestSubject::build( id: self::SUBJECT_ID ),
			mainContent: new WikitextContent( self::PAGE_TEXT )
		);

		$edit = $this->changeSubjectsOfPage(
			self::DIFFED_PAGE,
			TestSubject::build( id: self::SUBJECT_ID, label: 'Edited label' )
		);

		return $this->renderDiff( [ 'diff' => 'prev', 'oldid' => (string)$edit->getId() ] );
	}

	/**
	 * @param array<string, string> $query
	 */
	private function renderDiff( array $query ): string {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( $query ) );
		$context->setTitle( Title::newFromText( self::DIFFED_PAGE ) );

		Article::newFromTitle( $context->getTitle(), $context )->view();
		NeoWikiHooks::onBeforePageDisplay( $context->getOutput(), $context->getSkin() );

		return $context->getOutput()->getHTML();
	}

	private static function viewOf( string $subjectId ): string {
		return 'data-mw-neowiki-subject-id="' . $subjectId . '"';
	}

	private static function htmlAboveTheDiff( string $html ): string {
		return strstr( $html, '<table class="diff', true );
	}

	private static function htmlFromTheRevisionHeadingToThePageText( string $html ): string {
		return strstr( strstr( $html, 'diff-currentversion-title' ), self::PAGE_TEXT, true );
	}

}
