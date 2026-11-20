<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\PagePropertiesSource;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectsToPublish;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\UnreadableSubjectsException;
use Psr\Log\LoggerInterface;

class GraphStoreProjector {

	public function __construct(
		private readonly GraphDatabasePlugin $graphDatabasePlugin,
		private readonly PagePropertiesSource $pagePropertiesSource,
		private readonly RevisionPolicy $revisionPolicy,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Projects the revision the registered policy publishes for the page — with no Subjects when that revision
	 * holds none: every page gets a Page node, so its Page Properties are queryable. A page the policy publishes
	 * nothing for is withdrawn.
	 *
	 * @param RenderedRevision|null $rendering MediaWiki's rendering of a revision of the page, which spares a
	 *        parse when it is of the revision the policy publishes
	 */
	public function projectRevision( RevisionRecord $revisionRecord, ?RenderedRevision $rendering ): PageRefreshOutcome {
		$pageId = new PageId( $revisionRecord->getPageId() );
		$published = $this->revisionPolicy->publishedRevision( $revisionRecord );

		if ( $published === null ) {
			$this->withdrawPage( $pageId );
			return PageRefreshOutcome::Unpublished;
		}

		try {
			$subjects = SubjectsToPublish::of( $published );
		} catch ( UnreadableSubjectsException $exception ) {
			// Logged rather than thrown: only an edit can repair the page, so a retry would fail the same way.
			$this->logger->warning(
				'NeoWiki did not publish page {page}: its revision {revision} cannot be read as Subject data, so '
				. 'the graph stores and the published Subject index keep what they held for the page until it '
				. 'publishes Subject data that can be. {message}',
				[
					'page' => $pageId->id,
					'revision' => $published->getId(),
					'message' => $exception->getMessage(),
					'exception' => $exception,
				]
			);

			return PageRefreshOutcome::SkippedUnreadableSubjects;
		}

		return $this->projectPage( $pageId, $published, $subjects, $rendering );
	}

	private function projectPage(
		PageId $pageId,
		RevisionRecord $published,
		PageSubjects $subjects,
		?RenderedRevision $rendering
	): PageRefreshOutcome {
		$properties = $this->pagePropertiesSource->getPagePropertiesFor( $published, self::parseOf( $published, $rendering ) );

		if ( $properties === null ) {
			return PageRefreshOutcome::SkippedUnreadablePageProperties;
		}

		$this->graphDatabasePlugin->savePage(
			new Page(
				id: $pageId,
				properties: $properties,
				subjects: $subjects
			)
		);

		return PageRefreshOutcome::Refreshed;
	}

	private static function parseOf( RevisionRecord $published, ?RenderedRevision $rendering ): ?ParserOutput {
		if ( $rendering === null || $rendering->getRevision()->getId() !== $published->getId() ) {
			return null;
		}

		return $rendering->getRevisionParserOutput( [ 'generate-html' => false ] );
	}

	public function withdrawPage( PageId $pageId ): void {
		$this->graphDatabasePlugin->deletePage( $pageId );
	}

}
