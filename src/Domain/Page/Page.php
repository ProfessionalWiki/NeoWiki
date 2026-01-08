<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

readonly class Page {

	public function __construct(
		private PageId $id,
		private PageProperties $properties,
		private PageSubjects $subjects,
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
