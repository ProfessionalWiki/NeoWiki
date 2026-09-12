<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

use InvalidArgumentException;
use OutOfBoundsException;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use RuntimeException;

class PageSubjects {

	private ?Subject $mainSubject;
	private SubjectMap $otherSubjects;

	public function __construct( ?Subject $mainSubject, SubjectMap $otherSubjects ) {
		$this->mainSubject = $mainSubject;
		$this->otherSubjects = $otherSubjects;
	}

	public static function newEmpty(): self {
		return new self( null, new SubjectMap() );
	}

	public function getMainSubject(): ?Subject {
		return $this->mainSubject;
	}

	public function getOtherSubjects(): SubjectMap {
		return $this->otherSubjects;
	}

	public function getAllSubjects(): SubjectMap {
		return $this->otherSubjects->prepend( $this->mainSubject );
	}

	public function hasSubjects(): bool {
		return $this->mainSubject !== null
			|| !$this->otherSubjects->isEmpty();
	}

	public function hasMainSubject(): bool {
		return $this->mainSubject !== null;
	}

	public function isEmpty(): bool {
		return $this->mainSubject === null
			&& $this->otherSubjects->isEmpty();
	}

	public function setMainSubject( Subject $subject ): void {
		$this->mainSubject = $subject;
	}

	public function removeSubject( SubjectId $id ): void {
		if ( $this->isMainSubject( $id ) ) {
			$this->mainSubject = null;
		}
		else {
			$this->otherSubjects = $this->otherSubjects->without( $id );
		}
	}

	/**
	 * A copy without the given Subject, leaving this instance untouched.
	 */
	public function without( SubjectId $id ): self {
		return new self(
			$this->isMainSubject( $id ) ? null : $this->mainSubject,
			$this->otherSubjects->without( $id )
		);
	}

	/**
	 * Updates the subject with the ID of the provided subject.
	 * @throws OutOfBoundsException if the subject is not found
	 */
	public function updateSubject( Subject $subject ): void {
		if ( $this->isMainSubject( $subject->id ) ) {
			$this->mainSubject = $subject;
			return;
		}

		if ( $this->otherSubjects->hasSubject( $subject->id ) ) {
			$this->otherSubjects->addOrUpdateSubject( $subject );
			return;
		}

		throw new OutOfBoundsException( 'Subject not found' );
	}

	public function isMainSubject( SubjectId $id ): bool {
		return $this->mainSubject !== null && $this->mainSubject->id->equals( $id );
	}

	public function createMainSubject( Subject $subject ): void {
		if ( $this->mainSubject !== null ) {
			throw new RuntimeException( 'Main subject already exists' );
		}

		if ( $this->otherSubjects->hasSubject( $subject->id ) ) {
			throw new RuntimeException( 'Subject already exists' );
		}

		$this->mainSubject = $subject;
	}

	public function createOtherSubject( Subject $subject ): void {
		if ( $this->hasSubjectWithId( $subject->id ) ) {
			throw new RuntimeException( 'Subject already exists' );
		}

		$this->otherSubjects->addOrUpdateSubject( $subject );
	}

	private function hasSubjectWithId( SubjectId $id ): bool {
		return $this->isMainSubject( $id ) || $this->otherSubjects->hasSubject( $id );
	}

	/**
	 * Atomically set the main subject and the ordering of the other subjects. The set of
	 * ids in $mainId (if non-null) and $otherIds must match exactly the set of
	 * subject ids currently on this page; no additions or removals are allowed.
	 *
	 * @param SubjectId[] $otherIds
	 * @throws InvalidArgumentException on unknown / duplicate / missing ids
	 */
	public function setOrdering( ?SubjectId $mainId, array $otherIds ): void {
		$allSubjects = $this->getAllSubjects();

		$newMain = null;
		if ( $mainId !== null ) {
			$newMain = $allSubjects->getSubject( $mainId );
			if ( $newMain === null ) {
				throw new InvalidArgumentException( 'Unknown main subject id: ' . $mainId->text );
			}
		}

		$remaining = $mainId === null ? $allSubjects : $allSubjects->without( $mainId );
		$newOthers = $remaining->withOrdering( $otherIds );

		$this->mainSubject = $newMain;
		$this->otherSubjects = $newOthers;
	}

}
