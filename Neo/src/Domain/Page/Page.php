<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class Page {

	public function __construct(
		private readonly PageId $id,
		private readonly PageProperties $properties,
		private readonly ?Subject $mainSubject,
		private readonly SubjectMap $childSubjects
	) {
	}

	public function getId(): PageId {
		return $this->id;
	}

	public function getProperties(): PageProperties {
		return $this->properties;
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

}
