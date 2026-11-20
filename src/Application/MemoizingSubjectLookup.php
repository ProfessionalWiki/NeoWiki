<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

/**
 * Remembers the Subjects it read, and the ones it did not find, up to $capacity of them, forgetting the one
 * read longest ago first. For a run that reads the same Subjects over and over, such as checking pages whose
 * relations point at the same few Subjects. What it remembers goes out of date, so it suits only such runs.
 */
class MemoizingSubjectLookup implements SubjectLookup {

	/**
	 * @var array<string, ?Subject> By Subject id, the one read longest ago first
	 */
	private array $subjects = [];

	public function __construct(
		private readonly SubjectLookup $subjectLookup,
		private readonly int $capacity,
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getSubjects( new SubjectIdList( [ $subjectId ] ) )->getSubject( $subjectId );
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$this->readUnknown( $subjectIds );

		$subjects = [];

		foreach ( $subjectIds->asArray() as $subjectId ) {
			$subject = $this->subjects[$subjectId->text] ?? null;

			if ( $subject !== null ) {
				$subjects[] = $subject;
			}
		}

		return new SubjectMap( ...$subjects );
	}

	private function readUnknown( SubjectIdList $subjectIds ): void {
		$unknown = array_values( array_filter(
			$subjectIds->asArray(),
			fn ( SubjectId $id ): bool => !array_key_exists( $id->text, $this->subjects )
		) );

		if ( $unknown === [] ) {
			return;
		}

		$found = $this->subjectLookup->getSubjects( new SubjectIdList( $unknown ) );

		foreach ( $unknown as $subjectId ) {
			$this->remember( $subjectId->text, $found->getSubject( $subjectId ) );
		}
	}

	private function remember( string $subjectId, ?Subject $subject ): void {
		if ( count( $this->subjects ) >= $this->capacity ) {
			array_shift( $this->subjects );
		}

		$this->subjects[$subjectId] = $subject;
	}

}
