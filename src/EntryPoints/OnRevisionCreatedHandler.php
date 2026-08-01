<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Exception;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DBError;
use Wikimedia\RequestTimeout\TimeoutException;

class OnRevisionCreatedHandler {

	public function __construct(
		private readonly GraphDatabasePlugin $graphDatabasePlugin,
		private readonly PagePropertiesBuilder $pagePropertiesBuilder,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Projects the page of the given revision, with the Subjects it holds and with none when it holds
	 * none: every page gets a Page node, so its Page Properties are queryable.
	 */
	public function onRevisionCreated( RevisionRecord $revisionRecord, ?UserIdentity $user ): PageRefreshOutcome {
		if ( $revisionRecord->getPageId() === 0 ) {
			throw new \RuntimeException( 'Page ID should not be 0' );
		}

		$subjects = $this->getPageSubjects( $revisionRecord );

		if ( $subjects === null ) {
			$this->logSkip(
				$revisionRecord,
				'its subject slot holds content that is not Subject data, so projecting the page would drop '
				. 'the Subjects it holds from the graph'
			);
			return PageRefreshOutcome::SkippedUnreadableSubjects;
		}

		$properties = $this->getPageProperties( $revisionRecord, $user );

		if ( $properties === null ) {
			return PageRefreshOutcome::SkippedUnreadablePageProperties;
		}

		$this->graphDatabasePlugin->savePage(
			new Page(
				id: new PageId( $revisionRecord->getPageId() ),
				properties: $properties,
				subjects: $subjects
			)
		);

		return PageRefreshOutcome::Refreshed;
	}

	/**
	 * The Subjects the revision holds, none for a page without the subject slot, and null when the slot
	 * is present but does not hold Subject content — which happens when its content model is not
	 * registered, or an import wrote something else into it. Projecting such a page as holding no
	 * Subjects would wipe the Subjects it does hold from the graph, so nothing is written for it.
	 */
	private function getPageSubjects( RevisionRecord $revisionRecord ): ?PageSubjects {
		if ( !$revisionRecord->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) ) {
			return PageSubjects::newEmpty();
		}

		// The slot exists; a read failure here is a genuine error and must propagate —
		// the refresh contract treats genuine failures as exceptions, not skips.
		$content = $revisionRecord->getSlots()->getContent( MediaWikiSubjectRepository::SLOT_NAME );

		return $content instanceof SubjectContent ? $content->getPageSubjects() : null;
	}

	/**
	 * Building the properties parses the page and runs the registered providers, which can throw for a
	 * page MediaWiki can no longer fully handle, such as one whose content model an uninstalled extension
	 * owned. This runs inside the user's edit, so such a page is skipped and logged rather than allowed
	 * to abort that edit — the same trade the projection write itself makes, down to which throwables are
	 * let through (see FailureIsolatingGraphDatabasePlugin).
	 */
	private function getPageProperties( RevisionRecord $revisionRecord, ?UserIdentity $user ): ?PageProperties {
		try {
			return $this->pagePropertiesBuilder->getPagePropertiesFor( $revisionRecord, $user );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->logSkip( $revisionRecord, 'its page properties could not be built: ' . $e->getMessage() );
			return null;
		}
	}

	private function logSkip( RevisionRecord $revisionRecord, string $reason ): void {
		$this->logger->warning(
			'NeoWiki did not project page ' . $revisionRecord->getPageId() . ' because ' . $reason
			. '. The graph is out of sync for that page until the cause is resolved and the '
			. 'RebuildGraphDatabases maintenance script is run.'
		);
	}

	public function onPageDelete( int $pageId ): void {
		$this->graphDatabasePlugin->deletePage( new PageId( $pageId ) );
	}

}
