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

	/**
	 * Every read goes through the page hosting the Subject, and a page the reader may not read
	 * answers like a page without Subjects (ADR 27): the parse-time surfaces built on this
	 * resolver cannot tell a restricted page from an empty one. A Subject no page hosts resolves
	 * to nothing for the same reason: there is no page to read it off.
	 */
	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository,
		private readonly PageIdentifiersLookup $pageIdentifiersLookup,
		private readonly PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function resolveById( string $subjectIdText ): ?Subject {
		if ( !SubjectId::isValid( $subjectIdText ) ) {
			return null;
		}

		$subjectId = new SubjectId( $subjectIdText );

		try {
			$page = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId );

			return $page === null ? null : $this->getPageSubjects( $page->getId() )?->getAllSubjects()->getSubject( $subjectId );
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
		if ( !$this->readAuthorizer->authorizeReadByPageTitle( $title ) ) {
			return null;
		}

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
	 * One read, and one read check, per page for the resolver's lifetime: the relations a page
	 * renders mostly point at Subjects of that same page, and each would otherwise load the whole
	 * slot again. The Lua library keeps one resolver per parse; {{#neowiki_value}} builds one per
	 * call, so there the memo only spans that call's relation labels.
	 */
	private function getPageSubjects( PageId $pageId ): ?PageSubjects {
		if ( !array_key_exists( $pageId->id, $this->pageSubjectsByPageId ) ) {
			$this->pageSubjectsByPageId[$pageId->id] = $this->readAuthorizer->authorizeReadByPageId( $pageId )
				? $this->subjectContentRepository->getSubjectContentByPageId( $pageId )?->getPageSubjects()
				: null;
		}

		return $this->pageSubjectsByPageId[$pageId->id];
	}

}
