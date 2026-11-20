<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Closure;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

/**
 * For a write of one Subject based on an earlier revision of its page: whether what it would overwrite changed since,
 * and writing again on top of a save that lands between its read and its write.
 */
readonly class EditConflictCheck {

	public const string PAGE_KEPT_CHANGING = 'the page kept changing during the save';

	private const int ATTEMPTS = 3;

	public function __construct(
		private SubjectRepository $subjectRepository,
	) {
	}

	/**
	 * @param Closure(): bool $attempt Writes onto the revision it reads. False when a save landed after that read, so
	 *  it wrote and presented nothing.
	 * @return bool False when every attempt was overtaken.
	 */
	public function attemptUntilLanded( Closure $attempt ): bool {
		for ( $attempts = 0; $attempts < self::ATTEMPTS; $attempts++ ) {
			if ( $attempt() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @throws SubjectNotFoundException
	 */
	public function readLatest( PageId $pageId, SubjectId $subjectId ): SubjectAtRevision {
		$revisionId = $this->subjectRepository->getLatestRevisionId( $pageId );
		$pageSubjects = $revisionId === null ? null : $this->subjectRepository->getSubjectsAtRevision( $pageId, $revisionId );
		$subject = $pageSubjects?->getAllSubjects()->getSubject( $subjectId );

		if ( $revisionId === null || $pageSubjects === null || $subject === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		return new SubjectAtRevision( $subject, $pageSubjects, $revisionId );
	}

	/**
	 * A base revision that is not one of the page's, or does not hold the Subject, counts as a change.
	 *
	 * @param Closure(Subject, PageSubjects): SubjectMap $overwritten What of the Subject and its page the write
	 *  overwrites.
	 */
	public function changedSince( int $baseRevisionId, SubjectAtRevision $latest, PageId $pageId, Closure $overwritten ): bool {
		if ( $baseRevisionId === $latest->revisionId ) {
			return false;
		}

		$basePageSubjects = $this->subjectRepository->getSubjectsAtRevision( $pageId, $baseRevisionId );
		$baseSubject = $basePageSubjects?->getAllSubjects()->getSubject( $latest->subject->id );

		return $basePageSubjects === null
			|| $baseSubject === null
			|| !$overwritten( $baseSubject, $basePageSubjects )->hasSameContentAs(
				$overwritten( $latest->subject, $latest->pageSubjects )
			);
	}

}
