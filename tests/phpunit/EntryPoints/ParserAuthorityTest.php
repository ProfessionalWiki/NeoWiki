<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\Scribunto\ScribuntoContent;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\EntryPoints\ParserAuthority;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\ParseTimePermissionFixtures;
use WikiPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\ParserAuthority
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onParserOptionsRegister
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onParserFirstCallInit
 * @group Database
 */
class ParserAuthorityTest extends NeoWikiIntegrationTestCase {

	use ParseTimePermissionFixtures;

	private const string SUBJECT_PAGE = 'ParserAuthorityTestSubjectPage';
	private const string VALUE_FUNCTION = '{{#neowiki_value: Motto | page=' . self::SUBJECT_PAGE . ' }}';

	protected function setUp(): void {
		parent::setUp();

		$this->createPageWithSubjects(
			self::SUBJECT_PAGE,
			TestSubject::build( statements: new StatementList( [
				new Statement( new PropertyName( 'Motto' ), 'text', new StringValue( 'Cached per class' ) ),
			] ) )
		);
	}

	private function sysopOptions(): ParserOptions {
		return ParserOptions::newFromUser( $this->getTestSysop()->getUser() );
	}

	private function parse(
		string $wikitext,
		ParserOptions $parserOptions,
		string $pageName = 'ParserAuthorityTestPage'
	): ParserOutput {
		return $this->getServiceContainer()->getParserFactory()->create()->parse(
			$wikitext,
			Title::newFromText( $pageName ),
			$parserOptions
		);
	}

	/**
	 * Entries cached before NeoWiki keyed by access class carry no such key, so a gated page must not
	 * land on the key those entries used, not even for an anonymous reader.
	 */
	public function testAGatedPageDoesNotReuseAnEntryCachedWithoutTheAccessClass(): void {
		$anonymousOptions = ParserOptions::newFromAnon();

		$this->assertNotSame(
			$anonymousOptions->optionsHash( [] ),
			$anonymousOptions->optionsHash( [ ParserAuthority::ACCESS_CLASS_OPTION ] )
		);
	}

	/**
	 * Core compares every cache-varying option of the editor's options against the canonical ones on
	 * save, and defers the parser-cache update for every page when they differ.
	 */
	public function testEditorsOptionsStillMatchTheCanonicalOnesForPagesWithoutGatedReads(): void {
		$this->assertTrue( $this->sysopOptions()->matchesForCacheKey( ParserOptions::newFromAnon() ) );
	}

	public function testParseForALoggedInUserHasItsOwnCacheKey(): void {
		$this->assertNotSame(
			ParserOptions::newFromAnon()->optionsHash( [ ParserAuthority::ACCESS_CLASS_OPTION ] ),
			$this->sysopOptions()->optionsHash( [ ParserAuthority::ACCESS_CLASS_OPTION ] )
		);
	}

	public function testReadingASubjectPutsTheAccessClassInThePagesCacheKey(): void {
		$output = $this->parse( self::VALUE_FUNCTION, ParserOptions::newFromAnon() );

		$this->assertContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	/**
	 * The argument-less view reads its own page's Main Subject at parse time, and still only emits a marker.
	 */
	public function testRenderingAViewDoesNotFragmentTheCache(): void {
		$output = $this->parse( '{{#view}}', ParserOptions::newFromAnon(), self::SUBJECT_PAGE );

		$this->assertNotContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	public function testRunningACypherQueryPutsTheAccessClassInThePagesCacheKey(): void {
		$this->grantTheQueryRightToSysopsOnly();

		$output = $this->parse( '{{#cypher_raw: RETURN 1 AS n }}', ParserOptions::newFromAnon() );

		$this->assertContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	public function testRunningASparqlQueryPutsTheAccessClassInThePagesCacheKey(): void {
		$this->configureAnUnreachableSparqlStore();
		$this->grantTheQueryRightToSysopsOnly();

		$output = $this->parse( '{{#sparql_raw: SELECT * WHERE { ?s ?p ?o } }}', ParserOptions::newFromAnon() );

		$this->assertContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	public function testReadingASubjectFromLuaPutsTheAccessClassInThePagesCacheKey(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );
		$this->editPage(
			Title::makeTitle( NS_MODULE, 'ParserAuthorityTest' ),
			new ScribuntoContent(
				"local nw = require( 'mw.neowiki' )\n" .
				"local p = {}\n" .
				"function p.motto( frame ) return nw.getValue( 'Motto', { page = frame.args[1] } ) or '' end\n" .
				"return p"
			)
		);

		$output = $this->parse(
			'{{#invoke:ParserAuthorityTest|motto|' . self::SUBJECT_PAGE . '}}',
			ParserOptions::newFromAnon()
		);

		$this->assertContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	/**
	 * Wikitext holding a gated read, plus a Subject slot of its own, so the render combines two slots
	 * as every NeoWiki page does: the option the read records must survive that merge to reach the
	 * cache key.
	 */
	private function cachedGatedPage( ParserOptions $parserOptions ): WikiPage {
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_MAIN, 'ParserAuthorityTestCachedPage' ) );

		$updater = $page->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new WikitextContent( self::VALUE_FUNCTION ) );
		$updater->setContent(
			MediaWikiSubjectRepository::SLOT_NAME,
			SubjectContent::newFromData(
				new PageSubjects( TestSubject::build( id: 's1cached1111111' ), new SubjectMap() )
			)
		);
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Gated page' ) );

		$this->getServiceContainer()->getParserOutputAccess()->getParserOutput( $page, $parserOptions );

		return $page;
	}

	public function testCachedOutputOfOneClassIsNotServedToAnother(): void {
		$anonymousOptions = ParserOptions::newFromAnon();
		$page = $this->cachedGatedPage( $anonymousOptions );

		$parserCache = $this->getServiceContainer()->getParserCache();

		$this->assertNotFalse( $parserCache->get( $page, $anonymousOptions ), 'the anonymous parse is cached' );
		$this->assertFalse( $parserCache->get( $page, $this->sysopOptions() ), 'a sysop does not get it' );
	}

	public function testCachedOutputIsSharedWithinAnAccessClass(): void {
		$page = $this->cachedGatedPage( ParserOptions::newFromUser( $this->getTestUser( [ 'bot' ] )->getUser() ) );

		$anotherBot = ParserOptions::newFromUser( $this->getMutableTestUser( [ 'bot' ] )->getUser() );

		$this->assertNotFalse( $this->getServiceContainer()->getParserCache()->get( $page, $anotherBot ) );
	}

}
