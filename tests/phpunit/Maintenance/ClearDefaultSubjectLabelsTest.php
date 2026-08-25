<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\Language\RawMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Maintenance\ClearDefaultSubjectLabels;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

// The maintenance script is not PSR-4 autoloadable (it lives outside src/), so load it explicitly.
// Its RUN_MAINTENANCE_IF_MAIN guard is a no-op under PHPUnit, so this does not execute the script.
require_once __DIR__ . '/../../../maintenance/ClearDefaultSubjectLabels.php';

/**
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\ClearDefaultSubjectLabels
 * @group Database
 */
class ClearDefaultSubjectLabelsTest extends NeoWikiIntegrationTestCase {

	private const string MAIN_ID = 's11111111111111';
	private const string CHILD_ID = 's22222222222222';
	private const string SECOND_PAGE_MAIN_ID = 's33333333333333';
	private const string THIRD_PAGE_MAIN_ID = 's44444444444444';

	private string $scriptOutput = '';

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testAMainLabelRepeatingThePageNameIsCleared(): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'Vincent van Gogh' )
		);

		$this->runScript();

		$this->assertSame(
			[ self::MAIN_ID => null ],
			$this->getStoredLabels( 'Vincent van Gogh' )
		);
	}

	public function testAMainLabelSomebodyChoseIsKept(): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'The painter himself' )
		);

		$this->runScript();

		$this->assertSame(
			[ self::MAIN_ID => 'The painter himself' ],
			$this->getStoredLabels( 'Vincent van Gogh' )
		);
	}

	/**
	 * Before the child default became the Schema name, every Subject on a page was named after the page.
	 * Almost every child label a real wiki carries is of this era, so a heuristic that only knew about
	 * the Schema name would migrate next to nothing.
	 */
	public function testAChildLabelRepeatingThePageNameIsCleared(): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'Vincent van Gogh' ),
			new SubjectMap( TestSubject::build( id: self::CHILD_ID, label: 'Vincent van Gogh' ) )
		);

		$this->runScript();

		$this->assertSame(
			[ self::MAIN_ID => null, self::CHILD_ID => null ],
			$this->getStoredLabels( 'Vincent van Gogh' )
		);
	}

	public function testAChildLabelRepeatingTheSchemaNameIsCleared(): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'A chosen name' ),
			new SubjectMap( TestSubject::build( id: self::CHILD_ID, label: TestSubject::DEFAULT_SCHEMA_ID ) )
		);

		$this->runScript();

		$this->assertSame(
			[ self::MAIN_ID => 'A chosen name', self::CHILD_ID => null ],
			$this->getStoredLabels( 'Vincent van Gogh' )
		);
	}

	public function testAChildLabelSomebodyChoseIsKept(): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'A chosen name' ),
			new SubjectMap( TestSubject::build( id: self::CHILD_ID, label: 'Sunflowers' ) )
		);

		$this->runScript();

		$this->assertSame(
			[ self::MAIN_ID => 'A chosen name', self::CHILD_ID => 'Sunflowers' ],
			$this->getStoredLabels( 'Vincent van Gogh' )
		);
	}

	/**
	 * The editor filled the label from the unprefixed title while the graph carries the prefixed one, so
	 * a page outside the main namespace has a default in either form.
	 *
	 * @dataProvider namespacedPageNameProvider
	 */
	public function testBothFormsOfANamespacedPageNameAreDefaults( string $label ): void {
		$this->createPageWithSubjects(
			'Help:Painting styles',
			TestSubject::build( id: self::MAIN_ID, label: $label )
		);

		$this->runScript();

		$this->assertSame(
			[ self::MAIN_ID => null ],
			$this->getStoredLabels( 'Help:Painting styles' )
		);
	}

	public function namespacedPageNameProvider(): iterable {
		yield 'as the editor was shown it' => [ 'Painting styles' ];
		yield 'as the graph records it' => [ 'Help:Painting styles' ];
	}

	public function testDryRunReportsThePairsWithoutSavingThem(): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'Vincent van Gogh' )
		);

		$this->runScript( [ '--dry-run' ] );

		$this->assertSame(
			[ self::MAIN_ID => 'Vincent van Gogh' ],
			$this->getStoredLabels( 'Vincent van Gogh' ),
			'a dry run must leave every label where it was'
		);
		$this->assertStringContainsString( 'Vincent van Gogh: ' . self::MAIN_ID, $this->scriptOutput );
		$this->assertStringContainsString( 'Would clear 1 default labels', $this->scriptOutput );
	}

	public function testRunningItAgainFindsNothingLeftToClear(): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'Vincent van Gogh' )
		);
		$this->runScript();

		$this->runScript();

		$this->assertStringContainsString( 'Cleared 0 default labels on 0 pages.', $this->scriptOutput );
	}

	/**
	 * A wiki has more pages than one batch, and the run walks it by asking for the pages after the last
	 * one it handled. Clearing a label is what takes a page out of the next scan, so a run that stopped
	 * advancing would clear the first batch and then read it again for ever, finding nothing to do.
	 */
	public function testEveryBatchOfPagesIsCleared(): void {
		$this->createPageWithSubjects(
			'First painter',
			TestSubject::build( id: self::MAIN_ID, label: 'First painter' )
		);
		$this->createPageWithSubjects(
			'Second painter',
			TestSubject::build( id: self::SECOND_PAGE_MAIN_ID, label: 'Second painter' )
		);
		$this->createPageWithSubjects(
			'Third painter',
			TestSubject::build( id: self::THIRD_PAGE_MAIN_ID, label: 'Third painter' )
		);

		$this->runScript( [ '--batch-size=1' ] );

		$this->assertStringContainsString(
			'1/3 pages scanned',
			$this->scriptOutput,
			'precondition: the run read the wiki a batch at a time rather than in one go'
		);
		$this->assertSame( [ self::MAIN_ID => null ], $this->getStoredLabels( 'First painter' ) );
		$this->assertSame( [ self::SECOND_PAGE_MAIN_ID => null ], $this->getStoredLabels( 'Second painter' ) );
		$this->assertSame( [ self::THIRD_PAGE_MAIN_ID => null ], $this->getStoredLabels( 'Third painter' ) );
	}

	/**
	 * Rounding one of these up to the smallest batch that works would rewrite the whole wiki one page at
	 * a time under an option the operator got wrong, and say nothing about it. A batch of nothing is
	 * worse still: the scan would ask for no pages and keep asking.
	 *
	 * @dataProvider nonsensicalBatchSizeProvider
	 */
	public function testANonsensicalBatchSizeIsRefusedRatherThanRounded( string $batchSize ): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'Vincent van Gogh' )
		);

		$succeeded = $this->runScript( [ '--batch-size=' . $batchSize ] );

		$this->assertFalse( $succeeded );
		$this->assertStringContainsString( '--batch-size', $this->scriptOutput );
		$this->assertSame(
			[ self::MAIN_ID => 'Vincent van Gogh' ],
			$this->getStoredLabels( 'Vincent van Gogh' ),
			'nothing is cleared under an option that makes no sense'
		);
	}

	public function nonsensicalBatchSizeProvider(): iterable {
		yield 'nothing at all' => [ '0' ];
		yield 'less than nothing' => [ '-5' ];
		yield 'not a number' => [ 'lots' ];
	}

	/**
	 * A page the wiki refuses to save is the one thing an operator has to be told about: the run reports
	 * failure, so the exit status says the wiki still holds default labels, and names the page rather
	 * than leaving it to be found by reading every page again.
	 */
	public function testAPageThatCannotBeSavedIsReportedAsAFailure(): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'Vincent van Gogh' )
		);
		$this->refuseEverySave( 'Another extension said no' );

		$succeeded = $this->runScript();

		$this->assertFalse( $succeeded );
		$this->assertStringContainsString( 'Another extension said no', $this->scriptOutput );
		$this->assertSame(
			[ self::MAIN_ID => 'Vincent van Gogh' ],
			$this->getStoredLabels( 'Vincent van Gogh' ),
			'a label the wiki would not let go of is still there'
		);
	}

	public function testAPageWhoseSlotCannotBeReadIsReportedAndTheWalkGoesOn(): void {
		$this->createPageWithSubjects(
			'Vincent van Gogh',
			TestSubject::build( id: self::MAIN_ID, label: 'Vincent van Gogh' )
		);
		$this->indexPage(
			$this->createPageWithoutTellingNeoWiki(
				'Broken subject page',
				TestSubject::jsonThatDoesNotDeserialize( self::SECOND_PAGE_MAIN_ID )
			),
			self::SECOND_PAGE_MAIN_ID
		);
		// Written with the hook still cleared, so indexed by hand like the broken page before it.
		$this->indexPage(
			$this->createPageWithSubjects(
				'Paul Gauguin',
				TestSubject::build( id: self::THIRD_PAGE_MAIN_ID, label: 'Paul Gauguin' )
			)?->getPageId() ?? 0,
			self::THIRD_PAGE_MAIN_ID
		);

		$succeeded = $this->runScript();

		$this->assertFalse( $succeeded );
		$this->assertStringContainsString( 'Broken subject page', $this->scriptOutput );
		$this->assertSame( [ self::MAIN_ID => null ], $this->getStoredLabels( 'Vincent van Gogh' ) );
		$this->assertSame(
			[ self::THIRD_PAGE_MAIN_ID => null ],
			$this->getStoredLabels( 'Paul Gauguin' ),
			'the page after the broken one is still reached'
		);
	}

	/**
	 * Writes the slot as given, past every hook: the content is meant not to deserialize, which the
	 * projection would choke on.
	 */
	private function createPageWithoutTellingNeoWiki( string $pageName, string $subjectJson ): int {
		$this->clearHook( 'RevisionFromEditComplete' );

		$updater = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $pageName ) )
			->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new TextContent( '' ) );
		$updater->setContent( MediaWikiSubjectRepository::SLOT_NAME, new SubjectContent( $subjectJson ) );

		$revision = $updater->saveRevision( CommentStoreComment::newUnsavedComment( 'unhooked write' ) );
		$this->assertNotNull( $revision );

		return $revision->getPageId();
	}

	/**
	 * What the cleared hook would have done: the walk reads the index, so a page absent from it is
	 * never visited.
	 */
	private function indexPage( int $pageId, string $subjectId ): void {
		( new DatabaseSubjectPageIndex( $this->getDb() ) )->setSubjectsOfPage( new PageId( $pageId ), [ $subjectId ] );
	}

	public function testAWikiWithNoSubjectsHasNothingToScan(): void {
		$succeeded = $this->runScript();

		$this->assertTrue( $succeeded );
		$this->assertStringContainsString( 'Scanning 0 pages', $this->scriptOutput );
	}

	/**
	 * Drives the script the way the command line does, so the run covers option parsing too. It reports
	 * failure by returning false, which is what MaintenanceRunner turns into the exit status.
	 *
	 * @param string[] $arguments
	 */
	private function runScript( array $arguments = [] ): bool {
		$script = new ClearDefaultSubjectLabels();
		$script->loadWithArgv( $arguments );

		ob_start();
		try {
			$succeeded = $script->execute();
		} finally {
			$script->cleanupChanneled();
			$this->scriptOutput = (string)ob_get_clean();
		}

		return $succeeded;
	}

	/**
	 * Makes the wiki refuse every page save, the way an extension guarding what may be written does.
	 */
	private function refuseEverySave( string $reason ): void {
		$this->setTemporaryHook(
			'MultiContentSave',
			static function (
				RenderedRevision $renderedRevision,
				UserIdentity $user,
				CommentStoreComment $summary,
				int $flags,
				Status $status
			) use ( $reason ): bool {
				$status->fatal( new RawMessage( $reason ) );

				return false;
			}
		);
	}

	/**
	 * @return array<string, string|null> The stored label of every Subject on the page, keyed by subject id
	 */
	private function getStoredLabels( string $pageName ): array {
		$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle(
			Title::newFromText( $pageName )
		);
		$this->assertNotNull( $revision );

		$content = $revision->getContent( MediaWikiSubjectRepository::SLOT_NAME );
		$this->assertInstanceOf( SubjectContent::class, $content );

		$labels = [];

		foreach ( $content->getPageSubjects()->getAllSubjects()->asArray() as $subject ) {
			$labels[$subject->id->text] = $subject->getLabel()?->text;
		}

		return $labels;
	}

}
