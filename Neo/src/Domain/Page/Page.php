<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

class Page {

	public function __construct(
		private readonly PageId $id,
		private readonly PageProperties $properties,
		private readonly PageSubjects $subjects,
	) {
	}

	public function getId(): PageId {
		return $this->id;
	}

	public function getProperties(): PageProperties {
		return $this->properties;
	}

	public function getSubjects(): PageSubjects {
		return $this->subjects;
	}

}
