<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

use ProfessionalWiki\NeoWiki\Application\HostSubjectBuilder;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsAtRevisionLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;

/**
 * Adds the Host Subject to the summaries of Dependent Subjects nobody named, read from their pages: a Subject listing
 * does not hold it.
 */
readonly class SubjectSummaryHostSubjects {

	public function __construct(
		private DependentSubjectRules $dependentSubjectRules,
		private HostSubjectBuilder $hostSubjectBuilder,
		private PageSubjectsAtRevisionLookup $pageSubjectsLookup,
	) {
	}

	/**
	 * @param SubjectSummary[] $summaries
	 * @return SubjectSummary[]
	 */
	public function addTo( array $summaries ): array {
		$pagesRead = [];
		$withHostSubjects = [];

		foreach ( $summaries as $summary ) {
			$withHostSubjects[] = $this->withHostSubject( $summary, $pagesRead );
		}

		return $withHostSubjects;
	}

	/**
	 * @param array<int, PageSubjects> $pagesRead By page ID, filled as pages are read.
	 */
	private function withHostSubject( SubjectSummary $summary, array &$pagesRead ): SubjectSummary {
		if ( !$summary->displayNameIsGenerated
			|| !$this->dependentSubjectRules->localSchemaDeclaresDependentSubjects( $summary->schemaName )
		) {
			return $summary;
		}

		$pagesRead[$summary->pageId] ??= $this->pageSubjectsLookup->getPageSubjects( new PageId( $summary->pageId ) );
		$subject = $this->subjectWithId( $pagesRead[$summary->pageId], $summary->subjectId );

		if ( $subject === null ) {
			return $summary;
		}

		return new SubjectSummary(
			subjectId: $summary->subjectId,
			displayName: $summary->displayName,
			displayNameIsGenerated: $summary->displayNameIsGenerated,
			schemaName: $summary->schemaName,
			pageId: $summary->pageId,
			pageTitle: $summary->pageTitle,
			lastEdited: $summary->lastEdited,
			hostSubject: $this->hostSubjectBuilder->build( $subject, $pagesRead[$summary->pageId], $summary->pageTitle ),
		);
	}

	/**
	 * Compared as text, because the listing may hold an id that does not parse.
	 */
	private function subjectWithId( PageSubjects $pageSubjects, string $subjectId ): ?Subject {
		foreach ( $pageSubjects->getAllSubjects()->asArray() as $subject ) {
			if ( $subject->id->text === $subjectId ) {
				return $subject;
			}
		}

		return null;
	}

}
