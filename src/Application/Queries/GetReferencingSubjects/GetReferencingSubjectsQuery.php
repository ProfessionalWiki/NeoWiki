<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetReferencingSubjects;

use ProfessionalWiki\NeoWiki\Application\ReferencingSubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectHostingPageResolver;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectResponseItemFactory;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;

/**
 * The Subjects whose relations point at a given one.
 */
readonly class GetReferencingSubjectsQuery {

	/**
	 * Candidates are read past the limit so that dropped ones still tend to fill it; a long run of
	 * drops still shortens the answer.
	 */
	private const int OVERFETCH_FACTOR = 4;

	public function __construct(
		private GetReferencingSubjectsPresenter $presenter,
		private ReferencingSubjectLookup $referencingSubjectLookup,
		private SubjectLookup $subjectLookup,
		private SubjectHostingPageResolver $hostingPageResolver,
		private SubjectResponseItemFactory $responseItemFactory,
		private SubjectIdParser $subjectIdParser,
	) {
	}

	/**
	 * @param int<1, max> $limit How many referencing Subjects to return at most. The REST entry point
	 *   validates the range before this runs.
	 */
	public function execute( string $subjectId, int $limit ): void {
		$target = $this->subjectIdParser->parseOrThrow( $subjectId );

		// A Subject the caller may not read, and one the wiki does not publish, are answered like one
		// that is not there, so this endpoint confirms no harvested Subject id (ADR 27, #1046) and
		// answers as the Subject read does.
		if ( $this->hostingPageResolver->resolveReadableHostingPage( $target ) === null
			|| $this->subjectLookup->getSubject( $target ) === null ) {
			$this->presenter->presentReferencingSubjects( new GetReferencingSubjectsResponse( [], false ) );
			return;
		}

		$referrers = $this->verifiedReferrers( $target, $limit );

		$this->presenter->presentReferencingSubjects( new GetReferencingSubjectsResponse(
			referencingSubjects: $this->present( array_slice( $referrers, 0, $limit ) ),
			truncated: count( $referrers ) > $limit
		) );
	}

	/**
	 * The candidates that survive, in the order the lookup named them, with more than the limit
	 * collected so that a full page can be told from a complete one.
	 *
	 * Candidates are read a page at a time, so a Subject with far more referrers than the limit costs
	 * about as many Subject reads as it shows rows, rather than the whole over-fetched window.
	 *
	 * @param int<1, max> $limit
	 * @return array<array{Subject, PageIdentifiers, string[]}>
	 */
	private function verifiedReferrers( SubjectId $target, int $limit ): array {
		$candidateIds = $this->referencingSubjectLookup->getIdsOfSubjectsReferencing(
			$target,
			$limit * self::OVERFETCH_FACTOR
		);

		$referrers = [];

		foreach ( array_chunk( $candidateIds, $limit + 1 ) as $chunk ) {
			$referrers = array_merge( $referrers, $this->verifyChunk( $chunk, $target ) );

			if ( count( $referrers ) > $limit ) {
				break;
			}
		}

		return $referrers;
	}

	/**
	 * @param SubjectId[] $candidateIds
	 * @return array<array{Subject, PageIdentifiers, string[]}>
	 */
	private function verifyChunk( array $candidateIds, SubjectId $target ): array {
		$hostingPages = [];
		$readableIds = [];

		foreach ( $candidateIds as $candidateId ) {
			$hostingPage = $this->hostingPageResolver->resolveReadableHostingPage( $candidateId );

			if ( $hostingPage !== null ) {
				$hostingPages[$candidateId->text] = $hostingPage;
				$readableIds[] = $candidateId;
			}
		}

		$subjects = $this->subjectLookup->getSubjects( new SubjectIdList( $readableIds ) );

		$referrers = [];

		foreach ( $readableIds as $candidateId ) {
			$subject = $subjects->getSubject( $candidateId );

			if ( $subject === null ) {
				continue;
			}

			$propertyNames = $this->propertyNamesTargeting( $subject, $target );

			if ( $propertyNames !== [] ) {
				$referrers[] = [ $subject, $hostingPages[$candidateId->text], $propertyNames ];
			}
		}

		return $referrers;
	}

	/**
	 * Empty when it no longer points there at all, which is what makes a stale edge invisible here.
	 *
	 * @return string[]
	 */
	private function propertyNamesTargeting( Subject $subject, SubjectId $target ): array {
		$propertyNames = [];

		foreach ( $subject->getStatements()->asArray() as $statement ) {
			$value = $statement->getValue();

			if ( !$value instanceof RelationValue ) {
				continue;
			}

			foreach ( $value->relations as $relation ) {
				if ( $relation->targetId->equals( $target ) ) {
					$propertyNames[] = $statement->getPropertyName()->text;
					break;
				}
			}
		}

		return $propertyNames;
	}

	/**
	 * @param array<array{Subject, PageIdentifiers, string[]}> $referrers
	 * @return ReferencingSubject[]
	 */
	private function present( array $referrers ): array {
		$placedSubjects = [];

		foreach ( $referrers as [ $subject, $hostingPage, ] ) {
			$placedSubjects[$subject->getId()->text] = [ $subject, $hostingPage ];
		}

		// A row naming a Subject stored elsewhere is only followable when it says where that is.
		$items = $this->responseItemFactory->createResponseItems( $placedSubjects, includePageIdentifiers: true );

		$presented = [];

		foreach ( $referrers as [ $subject, , $propertyNames ] ) {
			$presented[] = new ReferencingSubject(
				subject: $items[$subject->getId()->text],
				propertyNames: $propertyNames
			);
		}

		return $presented;
	}

}
