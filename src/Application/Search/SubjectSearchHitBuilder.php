<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;

/**
 * Turns a page the search listed into what its row should say about the Subjects it holds: which
 * Subject the search matched, and whether the reader is better served by that Subject's own page
 * than by the page holding it.
 */
readonly class SubjectSearchHitBuilder {

	/**
	 * Enough for the reader to recognize the thing they searched for. Showing every matched value of
	 * a Subject that has many would bury the rows around it.
	 */
	private const int MAX_LINES = 3;

	public function __construct(
		private SubjectSearchTextBuilder $textBuilder
	) {
	}

	/**
	 * Null when the page's Subjects change nothing about its row.
	 *
	 * @param bool $pageHasContent Whether the page's main slot holds anything. Entity-first creation
	 *   writes an empty one on purpose, so a page without content is one that exists to hold a
	 *   Subject and has nothing of its own to show; its row leads to that Subject.
	 * @param ?SearchTermMatcher $matcher Null when the engine and the query together say nothing
	 *   about what was matched, which leaves the row unattributed but still retargeted.
	 */
	public function build(
		PageSubjects $pageSubjects,
		string $pageName,
		bool $pageHasContent,
		?SearchTermMatcher $matcher
	): ?SubjectSearchHit {
		$matchedSubject = $this->matchedSubject( $pageSubjects, $matcher );
		$landingSubject = $pageHasContent ? null : $pageSubjects->getMainSubject();

		if ( $landingSubject === null && $matchedSubject === null ) {
			return null;
		}

		return new SubjectSearchHit(
			landing: $landingSubject === null ? null : $this->landingFor( $landingSubject, $pageSubjects, $pageName ),
			match: $matchedSubject === null ? null : $this->matchFor( $matchedSubject, $pageSubjects, $pageName, $landingSubject !== null )
		);
	}

	/**
	 * The Main Subject is preferred over the page's other Subjects, since it is what the page is
	 * about; the rest follow in page order. One Subject is enough: a row names one thing.
	 */
	private function matchedSubject( PageSubjects $pageSubjects, ?SearchTermMatcher $matcher ): ?MatchedSubject {
		if ( $matcher === null ) {
			return null;
		}

		foreach ( $pageSubjects->getAllSubjects()->asArray() as $subject ) {
			$matchedLines = $this->matchedLines( $this->textBuilder->linesOf( $subject ), $matcher );

			if ( $matchedLines !== [] ) {
				return new MatchedSubject( $subject, $matchedLines );
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
	 * A page with nothing but its Main Subject would show the reader an empty page under a title
	 * nobody chose, so the row leads to the Subject instead.
	 */
	private function landingFor( Subject $subject, PageSubjects $pageSubjects, string $pageName ): SubjectSearchLanding {
		$chosenName = SubjectDisplayName::labelOrPageName( $subject, $pageSubjects, $pageName );

		return new SubjectSearchLanding(
			subjectId: $subject->getId(),
			subjectName: $chosenName ?? $subject->getSchemaName()->getText(),
			subjectNameIsGenerated: $chosenName === null
		);
	}

	private function matchFor( MatchedSubject $matchedSubject, PageSubjects $pageSubjects, string $pageName, bool $rowLinksToTheSubject ): SubjectSearchMatch {
		return new SubjectSearchMatch(
			schemaName: $matchedSubject->subject->getSchemaName()->getText(),
			subjectName: $rowLinksToTheSubject || $this->labelIsAmong( $matchedSubject->lines )
				? null
				: $this->nameTheRowDoesNotCarry( $matchedSubject->subject, $pageSubjects, $pageName ),
			lines: $matchedSubject->lines
		);
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
	 * The name to add to the row, where the row does not carry it already. It carries it as the page
	 * title of a Subject named after its page, and a generated name would only repeat the Schema
	 * name shown beside it.
	 */
	private function nameTheRowDoesNotCarry( Subject $subject, PageSubjects $pageSubjects, string $pageName ): ?string {
		$chosenName = SubjectDisplayName::labelOrPageName( $subject, $pageSubjects, $pageName );

		return $chosenName === $pageName ? null : $chosenName;
	}

}
