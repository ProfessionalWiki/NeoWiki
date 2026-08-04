<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\PagePropertiesSource;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;

class OnRevisionCreatedHandler {

	public function __construct(
		private readonly GraphDatabasePlugin $graphDatabasePlugin,
		private readonly PagePropertiesSource $pagePropertiesSource,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Projects the page of the given revision, with the Subjects it holds and with none when it holds
	 * none: every page gets a Page node, so its Page Properties are queryable.
	 */
	public function onRevisionCreated( RevisionRecord $revisionRecord, ?UserIdentity $user ): PageRefreshOutcome {
		if ( $revisionRecord->getPageId() === 0 ) {
			throw new RuntimeException( 'Page ID should not be 0' );
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

		// Null only from the isolating source the hook path is given, which has already logged the
		// cause. The rebuild path is given the propagating one, so there the failure surfaces to the
		// maintenance script instead, which reports it against the page.
		$properties = $this->pagePropertiesSource->getPagePropertiesFor( $revisionRecord, $user );

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
