<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class SubjectContentData {

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

	public function setChildSubjects( SubjectMap $subjects ): void {
		$this->childSubjects = $subjects;
	}

	public function removeSubject( SubjectId $id ): void {
		if ( $this->mainSubject !== null && $this->mainSubject->id->equals( $id ) ) {
			$this->mainSubject = null;
		}
		else {
			$this->childSubjects = $this->childSubjects->without( $id );
		}
	}

	public function updateSubject( Subject $subject ): void {
		if ( $this->mainSubject !== null && $this->mainSubject->id->equals( $subject->id ) ) {
			$this->mainSubject = $subject;
		}
		else {
			$this->childSubjects->addOrUpdateSubject( $subject );
		}
	}

}
