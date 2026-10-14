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

	private const string SUBJECT_ID = 's1zz1111111azz1';
	private const string OTHER_SUBJECT_ID = 's1zz1111111azz2';

	private const string PAGE_NAME = 'Auto render page';
	private const string PAGE_TEXT = 'Text of the page';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testDoesNotAutoRenderMainSubjectWhenConfigDisabled(): void {
		$this->createPageWithMainSubjectAndText();
		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$html = $this->renderRequest( [] );

		$this->assertStringNotContainsString( self::viewOf( self::SUBJECT_ID ), $html );
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

	private function createPageWithMainSubjectAndText(): void {
		$this->createPageWithSubjects(
			self::PAGE_NAME,
			TestSubject::build( id: self::SUBJECT_ID ),
			mainContent: new WikitextContent( self::PAGE_TEXT )
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
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( $query ) );
		$context->setTitle( Title::newFromText( self::PAGE_NAME ) );

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

	private static function htmlAboveThePageText( string $html ): string {
		return strstr( $html, self::PAGE_TEXT, true );
	}

	private static function htmlFromTheRevisionHeadingToThePageText( string $html ): string {
		return self::htmlAboveThePageText( strstr( $html, 'diff-currentversion-title' ) );
	}

}
