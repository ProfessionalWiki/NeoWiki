<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

/**
 * What it takes to name a Subject in a list, without reading the slot it lives in: everything
 * {@see SubjectDisplayName} needs except the page name, plus the Schema the Subject follows.
 *
 * Read from the slot JSON without deserializing, so a Subject too broken to deserialize still has one.
 * That is also why the fields are nullable strings rather than the value objects they become: a header
 * describes what the slot said, which is not always a valid Subject.
 */
readonly class SubjectHeader {

	public function __construct(
		public string $id,
		public ?string $schemaName,
		public ?string $label,
		public bool $isMainSubject,
	) {
	}

}
