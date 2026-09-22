<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

use ProfessionalWiki\NeoWiki\Application\SubjectNamer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;

/**
 * What a page's Subjects add to its search result row: the Subject to lead to instead of the page,
 * and the Subject the search matched.
 */
readonly class SubjectSearchHitBuilder {

	/**
	 * Enough to recognize the match without burying the rows around it.
	 */
	private const int MAX_LINES = 3;

	public function __construct(
		private SubjectSearchTextBuilder $textBuilder,
		private SubjectNamer $subjectNamer,
	) {
	}

	/**
	 * Null when the page's Subjects change nothing about its row.
	 *
	 * @param bool $pageHasContent Whether the main slot holds anything. A page without content exists
	 *   only to hold its Subject (entity-first creation writes an empty main slot), so its row leads
	 *   to that Subject.
	 * @param ?SearchTermMatcher $matcher Null leaves the row unattributed but still retargeted.
	 */
	public function build(
		PageSubjects $pageSubjects,
		string $pageName,
		bool $pageHasContent,
		?SearchTermMatcher $matcher
	): ?SubjectSearchHit {
		$landing = $pageHasContent ? null : $this->landingFor( $pageSubjects, $pageName );
		$match = $matcher === null
			? null
			: $this->matchFor( $pageSubjects, $pageName, $landing?->subjectName ?? $pageName, $matcher );

		if ( $landing === null && $match === null ) {
			return null;
		}

		return new SubjectSearchHit( $landing, $match );
	}

	/**
	 * Null for a page without a Main Subject.
	 */
	private function landingFor( PageSubjects $pageSubjects, string $pageName ): ?SubjectSearchLanding {
		$subject = $pageSubjects->getMainSubject();

		if ( $subject === null ) {
			return null;
		}

		$chosenName = $this->subjectNamer->chosenName( $subject, $pageSubjects, $pageName );

		return new SubjectSearchLanding(
			subjectId: $subject->getId(),
			subjectName: $chosenName ?? $subject->getSchemaName()->getText(),
			subjectNameIsGenerated: $chosenName === null
		);
	}

	/**
	 * The Main Subject first, then page order; one Subject per row.
	 *
	 * @param string $rowTitle The row's link text, which the extract need not repeat
	 */
	private function matchFor(
		PageSubjects $pageSubjects,
		string $pageName,
		string $rowTitle,
		SearchTermMatcher $matcher
	): ?SubjectSearchMatch {
		foreach ( $pageSubjects->getAllSubjects()->asArray() as $subject ) {
			$lines = $this->matchedLines( $this->textBuilder->linesOf( $subject ), $matcher );

			if ( $lines !== [] ) {
				return new SubjectSearchMatch(
					schemaName: $subject->getSchemaName()->getText(),
					subjectName: $this->labelIsAmong( $lines )
						? null
						: $this->nameTheRowDoesNotCarry( $subject, $pageSubjects, $pageName, $rowTitle ),
					lines: $lines
				);
			}
		}

		return null;
	}

	/**
	 * @param SubjectSearchLine[] $lines
	 * @return MatchedSearchLine[]
	 */
	private function matchedLines( array $lines, SearchTermMatcher $matcher ): array {
		$matchedLines = [];

		foreach ( $lines as $line ) {
			$parts = $matcher->splitOnMatches( $line->text );

			if ( count( $parts ) > 1 ) {
				$matchedLines[] = new MatchedSearchLine( $line->propertyName, $parts );

				if ( count( $matchedLines ) === self::MAX_LINES ) {
					break;
				}
			}
		}

		return $matchedLines;
	}

	/**
	 * @param MatchedSearchLine[] $lines
	 */
	private function labelIsAmong( array $lines ): bool {
		foreach ( $lines as $line ) {
			if ( $line->propertyName === null ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Null when the row's link text already names the Subject, or when the name would be a generated
	 * one repeating the Schema name shown beside it.
	 */
	private function nameTheRowDoesNotCarry(
		Subject $subject,
		PageSubjects $pageSubjects,
		string $pageName,
		string $rowTitle
	): ?string {
		$chosenName = $this->subjectNamer->chosenName( $subject, $pageSubjects, $pageName );

		return $chosenName === $rowTitle ? null : $chosenName;
	}

}
