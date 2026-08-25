<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Exception;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class SubjectResolver {

	/**
	 * @var array<int, ?PageSubjects>
	 */
	private array $pageSubjectsByPageId = [];

	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository,
		private readonly SubjectLookup $subjectLookup,
		private readonly PageIdentifiersLookup $pageIdentifiersLookup,
	) {
	}

	public function resolveById( string $subjectIdText ): ?Subject {
		if ( !SubjectId::isValid( $subjectIdText ) ) {
			return null;
		}

		try {
			return $this->subjectLookup->getSubject( new SubjectId( $subjectIdText ) );
		} catch ( Exception ) {
			return null;
		}
	}

	public function resolveMainByPageName( string $pageName ): ?Subject {
		$title = Title::newFromText( $pageName );

		if ( $title === null ) {
			return null;
		}

		return $this->resolveMainByTitle( $title );
	}

	public function resolveMainByTitle( Title $title ): ?Subject {
		return $this->getPageSubjectsByTitle( $title )?->getMainSubject();
	}

	public function getPageSubjectsByTitle( Title $title ): ?PageSubjects {
		return $this->subjectContentRepository
			->getSubjectContentByPageTitle( $title )
			?->getPageSubjects();
	}

	/**
	 * A relation target need not carry a label, so the display name stands in for one. The target is
	 * read off the page hosting it, which supplies both inputs the fallback needs: a label-less target
	 * that is its page's Main Subject reads as the page name, and any other reads as its Schema name.
	 * That is the name the target takes everywhere else it is shown. The target ID remains the last
	 * resort, for a target that does not resolve at all.
	 */
	public function resolveRelationLabel( Relation $relation ): string {
		try {
			$displayName = $this->resolveDisplayNameOnHostingPage( $relation->targetId );

			if ( $displayName !== null ) {
				return $displayName;
			}
		} catch ( Exception ) {
			// Fall through to ID
		}

		return $relation->targetId->text;
	}

	/**
	 * Reading the hosting page costs no more than asking the Subject lookup for the Subject alone:
	 * that lookup resolves the page and loads all its Subjects too, then drops the placement.
	 */
	private function resolveDisplayNameOnHostingPage( SubjectId $subjectId ): ?string {
		$page = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId );

		if ( $page === null ) {
			return null;
		}

		$pageSubjects = $this->getPageSubjects( $page->getId() );
		$subject = $pageSubjects?->getAllSubjects()->getSubject( $subjectId );

		if ( $pageSubjects === null || $subject === null ) {
			return null;
		}

		return SubjectDisplayName::forSubjectIn( $subject, $pageSubjects, $page->getTitle() );
	}

	/**
	 * One read per page for the resolver's lifetime, which is one parse: the relations a page renders
	 * mostly point at Subjects of that same page, and each would otherwise load the whole slot again.
	 */
	private function getPageSubjects( PageId $pageId ): ?PageSubjects {
		if ( !array_key_exists( $pageId->id, $this->pageSubjectsByPageId ) ) {
			$this->pageSubjectsByPageId[$pageId->id] = $this->subjectContentRepository
				->getSubjectContentByPageId( $pageId )
				?->getPageSubjects();
		}

		return $this->pageSubjectsByPageId[$pageId->id];
	}

}
