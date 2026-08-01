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
 * Every page is exported, with the Subjects it holds and with none when it holds none, which keeps the
 * RDF surfaces describing the same pages as the graph databases. Returns null when the page does not
 * exist, and for the one page state the write path also refuses to touch: a subject slot holding content
 * that is not Subject data, which the export must not describe as a page without Subjects.
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
		$subjects = $this->getPageSubjects( $revision );

		if ( $subjects === null ) {
			return null;
		}

		return new Page(
			id: new PageId( $revision->getPageId() ),
			properties: $this->pagePropertiesBuilder->getPagePropertiesFor( $revision, $revision->getUser() ),
			subjects: $subjects
		);
	}

	private function getPageSubjects( RevisionRecord $revision ): ?PageSubjects {
		if ( !$revision->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) ) {
			return PageSubjects::newEmpty();
		}

		$content = $revision->getSlots()->getContent( MediaWikiSubjectRepository::SLOT_NAME );

		return $content instanceof SubjectContent ? $content->getPageSubjects() : null;
	}

}
