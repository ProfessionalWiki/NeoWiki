<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Content\TextContent;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Page\PageRecord;
use MediaWiki\Parser\ParserOptions;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * A page showing the Subjects of another page is no longer served from the parser cache once those
 * Subjects change, rather than until its cache entry expires.
 *
 * Needs the integration base and the database for what ties the two pages together: the real parser
 * and parser cache, the links a save records, and the jobs a change queues.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onRevisionFromEditComplete
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\ParserPageDependencyRecorder
 * @group Database
 */
class CrossPageSubjectReadInvalidationTest extends NeoWikiIntegrationTestCase {

	private const string READ_PAGE = 'CrossPageSubjectReadInvalidationTestReadPage';
	private const string READING_PAGE = 'CrossPageSubjectReadInvalidationTestReadingPage';

	protected function setUp(): void {
		parent::setUp();

		$this->createPageWithSubjects( self::READ_PAGE, $this->subjectWithMotto( 'Before the change' ) );
		$this->editPage( self::READING_PAGE, '{{#neowiki_value: Motto | page=' . self::READ_PAGE . ' }}' );
		$this->runPendingUpdates();

		$this->cacheTheReadingPage();
		$this->waitForTheNextSecond();
	}

	private function subjectWithMotto( string $motto ): Subject {
		return TestSubject::build( statements: new StatementList( [ TestStatement::build( 'Motto', $motto ) ] ) );
	}

	private function runPendingUpdates(): void {
		DeferredUpdates::doUpdates();
		$this->runJobs( [ 'minJobs' => 0 ] );
	}

	private function cacheTheReadingPage(): void {
		$this->getServiceContainer()->getParserOutputAccess()->getParserOutput( $this->readingPage(), $this->viewOptions() );

		$this->assertTrue( $this->readingPageIsServedFromCache(), 'the reading page is served from cache before the change' );
	}

	/**
	 * Loaded afresh each time: a purge shows as a newer touched time, which a page loaded before it
	 * does not know about.
	 */
	private function readingPage(): PageRecord {
		$page = $this->getServiceContainer()->getPageStore()
			->getPageByName( NS_MAIN, self::READING_PAGE, IDBAccessObject::READ_LATEST );

		$this->assertNotNull( $page );

		return $page;
	}

	private function viewOptions(): ParserOptions {
		return ParserOptions::newFromAnon();
	}

	private function readingPageIsServedFromCache(): bool {
		return $this->getServiceContainer()->getParserCache()->get( $this->readingPage(), $this->viewOptions() ) !== false;
	}

	/**
	 * What an anonymous reader of the reading page gets, through the parser cache as on a page view.
	 * Parser output access also holds the current request's outputs in memory per revision, and a purge
	 * makes no revision, so a new instance stands in for the next request's.
	 */
	private function readingPageAsViewed(): string {
		$this->getServiceContainer()->resetServiceForTesting( 'ParserOutputAccess' );

		return $this->getServiceContainer()->getParserOutputAccess()
			->getParserOutput( $this->readingPage(), $this->viewOptions() )
			->getValue()->getContentHolderText();
	}

	/**
	 * The parser cache compares an entry with its page's touched time to the second, and saving the
	 * reading page and purging it both stamp PHP's own clock, which ConvertibleTimestamp cannot fake: a
	 * purge within the second the entry was cached in would look like no purge at all.
	 */
	private function waitForTheNextSecond(): void {
		$second = time();

		while ( time() === $second ) {
			usleep( 10_000 );
		}
	}

	public function testReaderSeesTheNewValueAfterOnlyTheSubjectsOfTheReadPageChange(): void {
		$this->changeSubjectsOfPage( self::READ_PAGE, $this->subjectWithMotto( 'After the change' ) );
		$this->runPendingUpdates();

		$this->assertStringContainsString( 'After the change', $this->readingPageAsViewed() );
	}

	public function testReaderSeesTheNewValueAfterTheReadPageIsSavedWithNewTextAndSubjects(): void {
		$this->createPageWithSubjects(
			self::READ_PAGE,
			$this->subjectWithMotto( 'After the change' ),
			mainContent: new TextContent( 'Text written along with the Subjects' )
		);
		$this->runPendingUpdates();

		$this->assertStringContainsString( 'After the change', $this->readingPageAsViewed() );
	}

}
