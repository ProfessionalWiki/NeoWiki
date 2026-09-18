<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Search;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * What a reader of Special:Search sees of the Subjects behind a hit, against a real full-text index.
 * Needs a database that has one, which is MySQL, and SQLite when PHP's SQLite was built with FTS3.
 *
 * Rendered in qqx, so what is asserted of NeoWiki's own strings is which message carries them.
 *
 * @group Database
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onShowSearchHit
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onShowSearchHitTitle
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onSearchGetNearMatchComplete
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Search\SubjectSearchHitLookup
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SubjectSearchHitHtmlBuilder
 */
class SubjectSearchHitRenderingTest extends FullTextSearchTestCase {

	use NeoWikiMockAuthorityTrait;

	private const string MAIN_ID = 's1search8aaaaaa';
	private const string OTHER_ID = 's1search8bbbbbb';

	public function testAValueMatchOnAPageWithProseShowsThePropertyAndTheMatchingValue(): void {
		$this->createPageWithProse( 'Rijksmuseum', $this->museumIn( 'Amsterdam' ) );

		$html = $this->searchResultsFor( 'Amsterdam' );

		$this->assertStringContainsString( 'City(colon-separator)<span class="searchmatch">Amsterdam</span>', $html );
	}

	public function testAValueMatchOnAPageWithProseStillLeadsToThePage(): void {
		$this->createPageWithProse( 'Rijksmuseum', $this->museumIn( 'Amsterdam' ) );

		$html = $this->searchResultsFor( 'Amsterdam' );

		$this->assertStringContainsString( '>Rijksmuseum</a>', $html );
		$this->assertStringNotContainsString( 'Special:Subject/' . self::MAIN_ID, $html );
	}

	public function testAMatchOnAnotherSubjectOfThePageStaysOnThePageAndShowsThatSubject(): void {
		$this->createPageWithProse(
			'Rijksmuseum',
			$this->museumIn( 'Amsterdam' ),
			new SubjectMap( $this->gemaldegalerieIn( 'Kulturforum' ) )
		);

		$html = $this->searchResultsFor( 'Kulturforum' );

		$this->assertStringContainsString( '>Rijksmuseum</a>', $html );
		$this->assertStringNotContainsString( 'Special:Subject/', $html );
		$this->assertStringContainsString( '(neowiki-search-hit-subject: Gemaldegalerie, Museum)', $html );
	}

	public function testAPageThatExistsOnlyToHoldItsMainSubjectLeadsToTheSubject(): void {
		$this->createPageWithSubjects( self::MAIN_ID, $this->museumIn( 'Deventer' ) );
		DeferredUpdates::doUpdates();

		$html = $this->searchResultsFor( 'Deventer' );

		$this->assertStringContainsString( 'Special:Subject/' . self::MAIN_ID, $html );
		$this->assertStringContainsString( '>Rijksmuseum</a>', $html );
	}

	public function testALabellessSubjectIsShownUnderItsSchemaNameRatherThanItsId(): void {
		$this->createPageWithSubjects( self::MAIN_ID, TestSubject::build(
			id: self::MAIN_ID,
			label: null,
			schemaName: new SchemaName( 'Museum' ),
			statements: new StatementList( [ TestStatement::build( property: 'City', value: 'Deventer' ) ] )
		) );
		DeferredUpdates::doUpdates();

		$this->assertStringContainsString(
			'>(neowiki-subject-generated-name: Museum)</a>',
			$this->searchResultsFor( 'Deventer' )
		);
	}

	public function testARowLeadingToASubjectShowsNoPageSize(): void {
		$this->createPageWithSubjects( self::MAIN_ID, $this->museumIn( 'Deventer' ) );
		DeferredUpdates::doUpdates();

		$html = $this->searchResultsFor( 'Deventer' );

		$this->assertStringContainsString( 'Special:Subject/' . self::MAIN_ID, $html );
		$this->assertStringNotContainsString( '(search-result-size', $html );
	}

	public function testARowLeadingToThePageKeepsThePageSize(): void {
		$this->createPageWithProse( 'Rijksmuseum', $this->museumIn( 'Amsterdam' ) );

		$this->assertStringContainsString( '(search-result-size', $this->searchResultsFor( 'Amsterdam' ) );
	}

	public function testALabelWithMarkupIsEscapedInTheLinkText(): void {
		$this->createPageWithSubjects( self::MAIN_ID, TestSubject::build(
			id: self::MAIN_ID,
			label: 'Deventer <b>museum</b>',
			schemaName: new SchemaName( 'Museum' )
		) );
		DeferredUpdates::doUpdates();

		$this->assertStringContainsString(
			'>Deventer &lt;b&gt;museum&lt;/b&gt;</a>',
			$this->searchResultsFor( 'Deventer' )
		);
	}

	public function testAPageTheReaderCannotReadKeepsItsPlainRow(): void {
		$this->createPageWithSubjects( self::MAIN_ID, $this->museumIn( 'Deventer' ) );
		DeferredUpdates::doUpdates();

		$html = $this->searchResultsFor( 'Deventer', $this->authorityWithGlobalReadButNoPageRead() );

		// MediaWiki stores such a page under a capitalized first letter, as it does every title.
		$this->assertStringContainsString( '>' . ucfirst( self::MAIN_ID ) . '</a>', $html, 'The page is still listed' );
		$this->assertStringNotContainsString( 'Special:Subject/', $html );
		$this->assertStringNotContainsString( 'ext-neowiki-searchresult', $html );
	}

	public function testGoLeadsToTheMainSubjectOfAPageThatExistsOnlyToHoldIt(): void {
		$this->createPageWithSubjects( self::MAIN_ID, $this->museumIn( 'Deventer' ) );

		$this->assertSame(
			'Special:Subject/' . self::MAIN_ID,
			$this->goResultFor( self::MAIN_ID )?->getPrefixedText()
		);
	}

	public function testGoLeavesAPageWithProseAlone(): void {
		$this->createPageWithProse( 'Rijksmuseum', $this->museumIn( 'Amsterdam' ) );

		$this->assertSame( 'Rijksmuseum', $this->goResultFor( 'Rijksmuseum' )?->getPrefixedText() );
	}

	/**
	 * list=search&srwhat=nearmatch asks the same question of the same service, and the search API
	 * answers with pages.
	 */
	public function testAnApiNearMatchStillAnswersWithThePage(): void {
		$this->createPageWithSubjects( self::MAIN_ID, $this->museumIn( 'Deventer' ) );

		$this->assertSame(
			ucfirst( self::MAIN_ID ),
			$this->nearMatchFor( self::MAIN_ID, Title::newFromTextThrow( 'Special:Badtitle/dummy for API' ) )
				?->getPrefixedText()
		);
	}

	/**
	 * MediaWiki does not offer the hook that adds the Subject block for a file, so retargeting one
	 * would leave a row whose thumbnail and description describe a page it no longer leads to.
	 */
	public function testAFileIsLeftAlone(): void {
		$this->createPageWithSubjects( 'File:SearchDemo.png', $this->museumIn( 'Deventer' ) );
		DeferredUpdates::doUpdates();

		$html = $this->searchResultsFor( 'Deventer', namespaces: [ 'ns6' => '1' ] );

		$this->assertStringContainsString( 'SearchDemo.png', $html );
		$this->assertStringNotContainsString( 'Special:Subject/', $html );
	}

	private function museumIn( string $city ): Subject {
		return TestSubject::build(
			id: self::MAIN_ID,
			label: 'Rijksmuseum',
			schemaName: new SchemaName( 'Museum' ),
			statements: new StatementList( [ TestStatement::build( property: 'City', value: $city ) ] )
		);
	}

	private function gemaldegalerieIn( string $city ): Subject {
		return TestSubject::build(
			id: self::OTHER_ID,
			label: 'Gemaldegalerie',
			schemaName: new SchemaName( 'Museum' ),
			statements: new StatementList( [ TestStatement::build( property: 'City', value: $city ) ] )
		);
	}

	private function createPageWithProse(
		string $pageName,
		Subject $mainSubject,
		SubjectMap $otherSubjects = new SubjectMap()
	): void {
		$this->createPageWithSubjects(
			$pageName,
			$mainSubject,
			$otherSubjects,
			new WikitextContent( 'A museum, described in prose.' )
		);

		DeferredUpdates::doUpdates();
	}

	/**
	 * @param string[] $namespaces Extra request parameters selecting namespaces beyond the default
	 */
	private function searchResultsFor( string $term, ?Authority $authority = null, array $namespaces = [] ): string {
		$context = new RequestContext();
		$context->setTitle( SpecialPage::getTitleFor( 'Search' ) );
		$context->setRequest( new FauxRequest(
			[ 'search' => $term, 'fulltext' => '1', 'profile' => 'advanced' ] + $namespaces
		) );
		$context->setAuthority( $authority ?? $this->getTestSysop()->getAuthority() );
		// After the authority, which invalidates the context's cached language.
		$context->setLanguage( 'qqx' );

		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Search' );
		$this->assertNotNull( $page );
		$page->setContext( $context );
		$page->execute( null );

		return $context->getOutput()->getHTML();
	}

	/**
	 * As the Go button asks it: while a reader is being routed by Special:Search.
	 */
	private function goResultFor( string $term ): ?Title {
		return $this->nearMatchFor( $term, SpecialPage::getTitleFor( 'Search' ) );
	}

	private function nearMatchFor( string $term, Title $requestTitle ): ?Title {
		RequestContext::getMain()->setAuthority( $this->getTestSysop()->getAuthority() );
		RequestContext::getMain()->setTitle( $requestTitle );

		return $this->getServiceContainer()->getTitleMatcher()->getNearMatch( $term );
	}

}
