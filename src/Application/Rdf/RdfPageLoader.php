<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\PagePropertiesBuilder;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;

/**
 * Loads a {@see Page} domain object from a page's current revision so it can be projected to RDF.
 * The revision slot is the source of truth (as in OnRevisionCreatedHandler and the graph rebuild),
 * so the export reflects the stored data rather than the secondary graph projection.
 *
 * Returns null when the page does not exist or its current revision carries no Subject slot.
 */
class RdfPageLoader {

	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly PagePropertiesBuilder $pagePropertiesBuilder,
	) {
	}

	public function loadByPageId( PageId $pageId ): ?Page {
		$title = Title::newFromID( $pageId->id );

		if ( $title === null ) {
			return null;
		}

		return $this->loadByTitle( $title );
	}

	public function loadByTitle( Title $title ): ?Page {
		$revision = $this->wikiPageFactory->newFromTitle( $title )->getRevisionRecord();

		if ( $revision === null ) {
			return null;
		}

		return $this->buildPage( $revision );
	}

	private function buildPage( RevisionRecord $revision ): ?Page {
		$content = $this->getSubjectContent( $revision );

		if ( $content === null ) {
			return null;
		}

		$subjects = $content->getPageSubjects();

		return new Page(
			id: new PageId( $revision->getPageId() ),
			properties: $this->pagePropertiesBuilder->getPagePropertiesFor( $revision, $revision->getUser() ),
			subjects: new PageSubjects(
				mainSubject: $subjects->getMainSubject(),
				childSubjects: $subjects->getChildSubjects()
			)
		);
	}

	private function getSubjectContent( RevisionRecord $revision ): ?SubjectContent {
		if ( !$revision->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) ) {
			return null;
		}

		$content = $revision->getSlots()->getContent( MediaWikiSubjectRepository::SLOT_NAME );

		return $content instanceof SubjectContent ? $content : null;
	}

}
