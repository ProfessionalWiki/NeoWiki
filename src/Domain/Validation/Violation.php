<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class Violation {

	/**
	 * A Relation target naming a Source this wiki has no way to reach (ADR 23, "Relations across
	 * Sources"). Cross-Source relations open up once resolution for them exists.
	 */
	public const string UNRESOLVABLE_RELATION_TARGET_SOURCE = 'relation-target-unresolvable-source';

	/**
	 * @param ?SubjectId $subjectId The Subject the Violation is on; null for the Subject being validated.
	 */
	public function __construct(
		public ?PropertyName $propertyName,
		public string $code,
		public array $args = [],
		public ?int $valuePartIndex = null,
		public Severity $severity = Severity::Warning,
		public ?SubjectId $subjectId = null,
	) {
	}

	public function withPropertyName( PropertyName $name ): self {
		return new self(
			propertyName: $name,
			code: $this->code,
			args: $this->args,
			valuePartIndex: $this->valuePartIndex,
			severity: $this->severity,
			subjectId: $this->subjectId,
		);
	}

	public function withSubjectId( SubjectId $subjectId ): self {
		return new self(
			propertyName: $this->propertyName,
			code: $this->code,
			args: $this->args,
			valuePartIndex: $this->valuePartIndex,
			severity: $this->severity,
			subjectId: $subjectId,
		);
	}

	/**
	 * Whether this Violation should block writes under enforcement (ADR 26).
	 */
	public function isBlocking(): bool {
		return $this->severity === Severity::Error;
	}

}
