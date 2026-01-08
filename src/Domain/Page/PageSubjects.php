<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

use OutOfBoundsException;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use RuntimeException;

class PageSubjects {

	private ?Subject $mainSubject;
	private SubjectMap $childSubjects;

	public function __construct( ?Subject $mainSubject, SubjectMap $childSubjects ) {
		$this->mainSubject = $mainSubject;
		$this->childSubjects = $childSubjects;
	}

	public static function newEmpty(): self {
		return new self( null, new SubjectMap() );
	}

	public function getMainSubject(): ?Subject {
		return $this->mainSubject;
	}

	public function getChildSubjects(): SubjectMap {
		return $this->childSubjects;
	}

	public function getAllSubjects(): SubjectMap {
		return $this->childSubjects->prepend( $this->mainSubject );
	}

	public function hasSubjects(): bool {
		return $this->mainSubject !== null
			|| !$this->childSubjects->isEmpty();
	}

	public function isEmpty(): bool {
		return $this->mainSubject === null
			&& $this->childSubjects->isEmpty();
	}

	public function setMainSubject( Subject $subject ): void {
		$this->mainSubject = $subject;
	}

	public function removeSubject( SubjectId $id ): void {
		if ( $this->isMainSubject( $id ) ) {
			$this->mainSubject = null;
		}
		else {
			$this->childSubjects = $this->childSubjects->without( $id );
		}
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

		if ( $this->childSubjects->hasSubject( $subject->id ) ) {
			$this->childSubjects->addOrUpdateSubject( $subject );
			return;
		}

		throw new OutOfBoundsException( 'Subject not found' );
	}

	private function isMainSubject( SubjectId $id ): bool {
		return $this->mainSubject !== null && $this->mainSubject->id->equals( $id );
	}

	public function createMainSubject( Subject $subject ): void {
		if ( $this->mainSubject !== null ) {
			throw new RuntimeException( 'Main subject already exists' );
		}

		$this->mainSubject = $subject;
	}

	public function createChildSubject( Subject $subject ): void {
		if ( $this->childSubjects->hasSubject( $subject->id ) ) {
			throw new RuntimeException( 'Child subject already exists' );
		}

		$this->childSubjects->addOrUpdateSubject( $subject );
	}

}
