<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use Throwable;
use Wikimedia\Rdbms\DBError;
use Wikimedia\RequestTimeout\TimeoutException;
use MediaWiki\User\User;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Clears the Subject labels that were only ever the default the editor was shown.
 *
 * A Subject label used to be required, so every Subject carries one whether anybody chose it or not.
 * A stored default cannot be told apart from a chosen name, which is what keeps a renamed page from
 * renaming the Subjects that took their name from it. Clearing the defaults once lets the fallback
 * compute them from the page and the Schema from then on.
 *
 * The default has two eras, and both are cleared: before the child-Subject default became the Schema
 * name, every Subject on a page defaulted to the page name.
 */
class ClearDefaultSubjectLabels extends Maintenance {

	private const int DEFAULT_BATCH_SIZE = 50;

	/**
	 * @var string[] The pages this run could not save, and why
	 */
	private array $failures = [];

	private int $scannedPages = 0;
	private int $changedPages = 0;
	private int $clearedLabels = 0;
	private PageContentSaver $saver;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Clears the Subject labels that were left at the default the editor offered: the page name for ' .
			'a Main Subject, and the page name or the Schema name for a Child Subject. Run once, when ' .
			'upgrading to the version that made the label optional. Each page it changes costs a revision.'
		);
		$this->addOption(
			'dry-run',
			'Report the labels this would clear without saving anything.'
		);
		$this->setBatchSize( self::DEFAULT_BATCH_SIZE );
	}

	/**
	 * Reports failure by returning false rather than by exiting, which is what MaintenanceRunner turns
	 * into the process's exit status.
	 */
	public function execute(): bool {
		$batchSize = $this->getBatchSize();

		if ( $batchSize === null || $batchSize < 1 ) {
			$this->error( '--batch-size must be a whole number of pages, and at least 1.' );
			return false;
		}

		$this->saver = $this->newPageContentSaver();
		$totalPages = $this->countSubjectPages();

		$this->outputChanneled( 'Scanning ' . $totalPages . ' pages that carry a Subject.' );

		$afterPageId = 0;

		do {
			$pageIds = $this->subjectPageIdsAfter( $afterPageId, $batchSize );

			foreach ( $pageIds as $pageId ) {
				$afterPageId = $pageId;
				$this->clearPage( $pageId );
			}

			$this->scannedPages += count( $pageIds );
			$this->outputChanneled( $this->scannedPages . '/' . $totalPages . ' pages scanned' );
			$this->waitForReplication();
		} while ( count( $pageIds ) === $batchSize );

		$this->reportRun();

		return $this->failures === [];
	}

	/**
	 * The pages to walk come from the subject -> page index, which is what holds Subjects on a wiki that
	 * has run update.php since the index existed, and which the script's own saves keep current.
	 */
	private function countSubjectPages(): int {
		return (int)$this->getReplicaDB()->newSelectQueryBuilder()
			->select( 'COUNT(DISTINCT nwsp_page_id)' )
			->from( DatabaseSubjectPageIndex::TABLE )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * The next batch of page ids past $afterPageId, ascending. Paging by page id rather than by offset
	 * means a page created or deleted mid-walk cannot make the walk skip another one.
	 *
	 * @return int[]
	 */
	private function subjectPageIdsAfter( int $afterPageId, int $batchSize ): array {
		$db = $this->getReplicaDB();

		return array_map( 'intval', $db->newSelectQueryBuilder()
			->select( 'nwsp_page_id' )
			->distinct()
			->from( DatabaseSubjectPageIndex::TABLE )
			->where( $db->buildComparison( '>', [ 'nwsp_page_id' => $afterPageId ] ) )
			->orderBy( 'nwsp_page_id' )
			->limit( $batchSize )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

	private function reportRun(): void {
		$this->outputChanneled(
			( $this->isDryRun() ? 'Would clear ' : 'Cleared ' ) . $this->clearedLabels
			. ' default labels on ' . $this->changedPages . ' pages.'
		);

		if ( $this->isDryRun() ) {
			$this->outputChanneled( 'Nothing was saved. Re-run without --dry-run to clear them.' );
		}

		foreach ( $this->failures as $failure ) {
			$this->error( $failure );
		}
	}

	/**
	 * A page whose slot cannot be read as Subjects is reported and walked past, as the graph rebuild
	 * does: one such page must not stop every page after it from being cleared. Timeouts and database
	 * errors are not a page's fault and end the run.
	 */
	private function clearPage( int $pageId ): void {
		$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromID( $pageId );

		if ( $title === null ) {
			return;
		}

		try {
			$this->clearDefaultLabelsOfPage( $pageId, $title );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Throwable $e ) {
			$this->failures[] = $title->getPrefixedText() . ': ' . $e->getMessage();
		}
	}

	private function clearDefaultLabelsOfPage( int $pageId, Title $title ): void {
		$content = $this->getSubjectContent( $pageId );

		if ( $content === null ) {
			return;
		}

		$pageSubjects = $content->getPageSubjects();
		$defaultLabels = self::findDefaultLabels( $pageSubjects, $title );

		if ( $defaultLabels === [] ) {
			return;
		}

		$this->changedPages++;
		$this->clearedLabels += count( $defaultLabels );
		$this->reportDefaultLabels( $title, $defaultLabels );

		if ( $this->isDryRun() ) {
			return;
		}

		foreach ( $defaultLabels as $subject ) {
			$subject->setLabel( null );
		}

		$content->setPageSubjects( $pageSubjects );
		$this->save( $pageId, $title, $content );
	}

	private function getSubjectContent( int $pageId ): ?SubjectContent {
		$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByPageId( $pageId );

		if ( $revision === null ) {
			return null;
		}

		try {
			$content = $revision->getContent( MediaWikiSubjectRepository::SLOT_NAME );
		}
		catch ( RevisionAccessException ) {
			return null;
		}

		return $content instanceof SubjectContent ? $content : null;
	}

	/**
	 * A label counts as a default when it repeats a name NeoWiki would have shown without it.
	 *
	 * The page name is matched both prefixed and unprefixed because the two eras of the default read it
	 * differently: the editor filled the field from the unprefixed title, while the page node carries the
	 * prefixed one. Matching one form alone would pass over every page outside the main namespace.
	 *
	 * @return array<string, Subject> The Subjects whose label is a default, keyed by id text
	 */
	private static function findDefaultLabels( PageSubjects $pageSubjects, Title $title ): array {
		$defaultLabels = [];

		foreach ( $pageSubjects->getAllSubjects()->asArray() as $subject ) {
			$label = $subject->getLabel()?->text;

			if ( $label !== null && in_array( trim( $label ), self::defaultsFor( $subject, $pageSubjects, $title ), true ) ) {
				$defaultLabels[$subject->id->text] = $subject;
			}
		}

		return $defaultLabels;
	}

	/**
	 * @return string[] Every name this Subject could have been given by default
	 */
	private static function defaultsFor( Subject $subject, PageSubjects $pageSubjects, Title $title ): array {
		$pageNames = [ $title->getText(), $title->getPrefixedText() ];

		if ( $pageSubjects->isMainSubject( $subject->getId() ) ) {
			return $pageNames;
		}

		return [ ...$pageNames, $subject->getSchemaName()->getText() ];
	}

	/**
	 * @param array<string, Subject> $defaultLabels
	 */
	private function reportDefaultLabels( Title $title, array $defaultLabels ): void {
		foreach ( $defaultLabels as $subjectId => $subject ) {
			$this->outputChanneled(
				$title->getPrefixedText() . ': ' . $subjectId . ' "' . $subject->getLabel()?->text . '"'
			);
		}
	}

	private function save( int $pageId, Title $title, SubjectContent $content ): void {
		$status = $this->saver->saveContent(
			new PageId( $pageId ),
			[ MediaWikiSubjectRepository::SLOT_NAME => $content ],
			CommentStoreComment::newUnsavedComment( 'Clear default NeoWiki Subject labels' )
		);

		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->failures[] = $title->getPrefixedText() . ': ' . ( $status->errorMessage ?? 'Unknown error' );
		}
	}

	/**
	 * Built by hand rather than taken from the extension, whose saver performs as whoever the request
	 * belongs to. There is no request here, and attributing a wiki-wide rewrite to whatever the CLI
	 * resolves to is both wrong and liable to be refused on a protected page.
	 */
	private function newPageContentSaver(): PageContentSaver {
		return new PageContentSaver(
			wikiPageFactory: MediaWikiServices::getInstance()->getWikiPageFactory(),
			performer: User::newSystemUser( 'NeoWiki', [ 'steal' => true ] ),
		);
	}

	private function isDryRun(): bool {
		return $this->hasOption( 'dry-run' );
	}

}

$maintClass = ClearDefaultSubjectLabels::class;
require_once RUN_MAINTENANCE_IF_MAIN;
