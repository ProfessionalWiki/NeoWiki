<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Search;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectIndexingSearchEngine;
use RuntimeException;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * What an edit hands the wiki's search engine to index. Searching for it is
 * {@see \ProfessionalWiki\NeoWiki\Tests\EntryPoints\Search\DatabaseSearchEndToEndTest}.
 *
 * @group Database
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Search\IndexesSubjectText
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onRevisionFromEditComplete
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onPageUndeleteComplete
 */
class SubjectSearchIndexingTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		SpySubjectIndexingSearchEngine::forgetIndexedText();
		$this->overrideConfigValue( MainConfigNames::SearchType, SpySubjectIndexingSearchEngine::class );
	}

	public function testSubjectLabelAndValuesAreIndexed(): void {
		$pageId = $this->createSubjectPage( $this->museumIn( 'Amsterdam' ) );

		$this->assertStringContainsString( "rijksmuseum\namsterdam", $this->textIndexedFor( $pageId ) );
	}

	public function testEditingOnlyTheSubjectsUpdatesTheIndex(): void {
		$pageId = $this->createSubjectPage( $this->museumIn( 'Amsterdam' ) );

		$this->changeSubjectsOfPage( 'Museum page', $this->museumIn( 'Rotterdam' ) );
		DeferredUpdates::doUpdates();

		$this->assertStringContainsString( 'rotterdam', $this->textIndexedFor( $pageId ) );
	}

	public function testValueRemovedFromASubjectIsNoLongerIndexed(): void {
		$pageId = $this->createSubjectPage( $this->museumIn( 'Amsterdam' ) );

		$this->changeSubjectsOfPage( 'Museum page', TestSubject::build( label: 'Rijksmuseum' ) );
		DeferredUpdates::doUpdates();

		$this->assertStringNotContainsString( 'amsterdam', $this->textIndexedFor( $pageId ) );
	}

	/**
	 * Nothing is appended, not even the line a Subject's text would sit on.
	 */
	public function testPageWithoutSubjectsIsIndexedByItsOwnTextAlone(): void {
		$pageId = $this->insertPage( 'Plain page', 'Concertgebouw' )['id'];
		DeferredUpdates::doUpdates();

		$indexedText = $this->textIndexedFor( $pageId );

		$this->assertStringContainsString( 'concertgebouw', $indexedText );
		$this->assertStringNotContainsString( "\n", $indexedText );
	}

	/**
	 * Protecting a page, moving it, and another extension writing a slot of its own all insert such a
	 * revision, and none of them changes what the page is findable by.
	 */
	public function testARevisionThatChangesNoSubjectsIsNotIndexedAgain(): void {
		$pageId = $this->createSubjectPage( $this->museumIn( 'Amsterdam' ) );
		SpySubjectIndexingSearchEngine::forgetIndexedText();

		$this->saveRevisionInheritingEverySlot( 'Museum page' );
		DeferredUpdates::doUpdates();

		$this->assertNull( SpySubjectIndexingSearchEngine::textIndexedForPage( $pageId ) );
	}

	/**
	 * The revision has no Subject slot at all, rather than an inherited one.
	 */
	public function testANullRevisionOnAPageWithoutSubjectsIsNotIndexedAgain(): void {
		$pageId = $this->insertPage( 'Plain page', 'Concertgebouw' )['id'];
		DeferredUpdates::doUpdates();
		SpySubjectIndexingSearchEngine::forgetIndexedText();

		$this->saveRevisionInheritingEverySlot( 'Plain page' );
		DeferredUpdates::doUpdates();

		$this->assertNull( SpySubjectIndexingSearchEngine::textIndexedForPage( $pageId ) );
	}

	/**
	 * Core re-indexes an undeleted page only when the restored revision's main slot is not inherited,
	 * which for a page last saved from the Subject editor it is.
	 */
	public function testUndeletedPageIsIndexedByItsSubjectsAgain(): void {
		$this->createSubjectPage( $this->museumIn( 'Amsterdam' ) );
		$this->changeSubjectsOfPage( 'Museum page', $this->museumIn( 'Rotterdam' ) );
		DeferredUpdates::doUpdates();

		$this->deletePageByName( 'Museum page' );
		SpySubjectIndexingSearchEngine::forgetIndexedText();

		$this->undeletePageByName( 'Museum page' );
		DeferredUpdates::doUpdates();

		$this->assertStringContainsString( 'rotterdam', $this->textIndexedFor( $this->idOfPage( 'Museum page' ) ) );
	}

	private function undeletePageByName( string $pageName ): void {
		$undeletePage = MediaWikiServices::getInstance()->getUndeletePageFactory()->newUndeletePage(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) ),
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $undeletePage->undeleteUnsafe( 'test undeletion' ) );
	}

	private function idOfPage( string $pageName ): int {
		$page = $this->getServiceContainer()->getPageStore()
			->getPageByName( NS_MAIN, str_replace( ' ', '_', $pageName ), IDBAccessObject::READ_LATEST );

		if ( $page === null ) {
			throw new RuntimeException( "There is no page $pageName" );
		}

		return $page->getId();
	}

	/**
	 * The label is what readers see and search for; the option id is what the Subject stores.
	 */
	public function testSelectValueIsIndexedByItsOptionLabel(): void {
		$this->createSchema( 'Museum', json_encode( [
			'title' => 'Museum',
			'propertyDefinitions' => [
				'Status' => [
					'type' => 'select',
					'options' => [
						[ 'id' => 'opt_planned', 'label' => 'Planned' ],
						[ 'id' => 'opt_open', 'label' => 'Open to visitors' ],
						[ 'id' => 'opt_closed', 'label' => 'Closed' ],
					],
				],
			],
		] ) );

		$pageId = $this->createSubjectPage( TestSubject::build(
			label: 'Rijksmuseum',
			schemaName: new SchemaName( 'Museum' ),
			statements: new StatementList( [
				TestStatement::build( property: 'Status', value: 'opt_open', propertyType: 'select' ),
			] )
		) );

		$this->assertStringContainsString( 'open to visitors', $this->textIndexedFor( $pageId ) );
		$this->assertStringNotContainsString( 'opt_open', $this->textIndexedFor( $pageId ) );
	}

	/**
	 * MediaWiki folds page text to lower case before indexing it, and SQLite's tokenizer folds ASCII
	 * letters only, so a Subject's text is folded the same way.
	 */
	public function testSubjectTextIsFoldedToLowerCaseLikePageText(): void {
		$pageId = $this->createSubjectPage( $this->museumIn( 'Ägypten' ) );

		$this->assertStringContainsString( 'ägypten', $this->textIndexedFor( $pageId ) );
	}

	private function saveRevisionInheritingEverySlot( string $pageName ): void {
		$updater = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $pageName ) )
			->newPageUpdater( $this->getTestSysop()->getUser() );

		$updater->setForceEmptyRevision( true );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'A null revision' ) );
	}

	private function createSubjectPage( Subject $mainSubject ): int {
		$revision = $this->createPageWithSubjects( 'Museum page', $mainSubject );
		$this->assertNotNull( $revision );
		DeferredUpdates::doUpdates();

		return $revision->getPageId();
	}

	private function museumIn( string $city ): Subject {
		return TestSubject::build(
			label: 'Rijksmuseum',
			statements: new StatementList( [ TestStatement::build( property: 'City', value: $city ) ] )
		);
	}

	private function textIndexedFor( int $pageId ): string {
		$text = SpySubjectIndexingSearchEngine::textIndexedForPage( $pageId );
		$this->assertNotNull( $text, 'The page was never indexed' );

		return $text;
	}

}
