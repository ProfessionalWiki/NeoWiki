<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Presentation\SubjectLabelHtml;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the database for the page whose Subjects the view reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBeforePageDisplay
 * @group Database
 */
class SubjectIdTitledPageHeadingTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID = 's1zz1111111azz5';

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testAPageTitledByItsSubjectsIdIsHeadedByTheSubjectsLabelAndId(): void {
		$heading = $this->viewContentPage( $this->createPageLabelled( 'Ada Lovelace' )->getId() )->getPageTitle();

		$this->assertStringContainsString( 'Ada Lovelace', $heading );
		$this->assertStringContainsString( self::SUBJECT_ID, $heading );
	}

	public function testTheBrowserTitleNamesThePageByTheLabelAlone(): void {
		$browserTitle = $this->viewContentPage( $this->createPageLabelled( 'Ada Lovelace' )->getId() )->getHTMLTitle();

		$this->assertStringContainsString( 'Ada Lovelace', $browserTitle );
		$this->assertStringNotContainsString( substr( self::SUBJECT_ID, 1 ), $browserTitle );
	}

	public function testAPageHeadedByItsSubjectsLabelCarriesTheStylesForTheId(): void {
		$out = $this->viewContentPage( $this->createPageLabelled( 'Ada Lovelace' )->getId() );

		$this->assertContains( SubjectLabelHtml::STYLE_MODULE, $out->getModuleStyles() );
	}

	public function testTheHeadingDoesNotDependOnTheMainSubjectBeingRendered(): void {
		$revisionId = $this->createPageLabelled( 'Ada Lovelace' )->getId();
		$this->overrideConfigValue( 'NeoWikiAutoRenderMainSubject', false );

		$this->assertStringContainsString( 'Ada Lovelace', $this->viewContentPage( $revisionId )->getPageTitle() );
	}

	public function testAnOldRevisionIsHeadedByTheLabelItHeld(): void {
		$oldRevisionId = $this->createPageLabelled( 'Ada Lovelace' )->getId();
		$this->changeSubjectsOfPage(
			self::SUBJECT_ID,
			TestSubject::build( id: self::SUBJECT_ID, label: 'Countess' )
		);

		$this->assertStringContainsString(
			'Ada Lovelace',
			$this->viewContentPage( $oldRevisionId )->getPageTitle()
		);
	}

	/**
	 * A diff shows the page below it, yet is headed by the diff, as core leaves a display title out
	 * of it too.
	 */
	public function testADiffKeepsItsOwnHeading(): void {
		$revisionId = $this->createPageLabelled( 'Ada Lovelace' )->getId();

		$out = $this->viewContentPage( $revisionId, [ 'diff' => 'prev', 'oldid' => (string)$revisionId ] );

		$this->assertStringNotContainsString( 'Ada Lovelace', $out->getPageTitle() );
	}

	/**
	 * An old revision the reader may not see, or one that does not exist, is replaced by an error,
	 * which core heads and leaves without a revision id.
	 */
	public function testAnErrorShownInPlaceOfTheRevisionKeepsItsHeading(): void {
		$this->createPageLabelled( 'Ada Lovelace' );

		$this->assertStringNotContainsString( 'Ada Lovelace', $this->viewContentPage( null )->getPageTitle() );
	}

	/**
	 * Core also puts an error in place of a revision it has already chosen, such as when too many
	 * readers wait for it to render.
	 */
	public function testAnErrorShownInPlaceOfAChosenRevisionKeepsItsHeading(): void {
		$out = $this->articleOutput( $this->createPageLabelled( 'Ada Lovelace' )->getId(), [] );
		$out->setPageTitleMsg( $out->msg( 'errorpagetitle' ) );

		$this->assertStringNotContainsString( 'Ada Lovelace', $this->display( $out )->getPageTitle() );
	}

	private function createPageLabelled( string $label ): RevisionRecord {
		return $this->createPageWithSubjects(
			self::SUBJECT_ID,
			TestSubject::build( id: self::SUBJECT_ID, label: $label )
		);
	}

	/**
	 * @param array<string, string> $query
	 */
	private function viewContentPage( ?int $revisionId, array $query = [] ): OutputPage {
		return $this->display( $this->articleOutput( $revisionId, $query ) );
	}

	/**
	 * @param array<string, string> $query
	 */
	private function articleOutput( ?int $revisionId, array $query ): OutputPage {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( $query ) );
		$context->setTitle( Title::newFromText( self::SUBJECT_ID ) );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $revisionId );

		return $out;
	}

	private function display( OutputPage $out ): OutputPage {
		NeoWikiHooks::onBeforePageDisplay( $out, $out->getSkin() );

		return $out;
	}

}
