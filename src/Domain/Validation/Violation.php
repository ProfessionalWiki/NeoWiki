<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;

readonly class Violation {

	/**
	 * A Relation target naming a Source this wiki has no way to reach (ADR 23, "Relations across
	 * Sources"). Cross-Source relations open up once resolution for them exists.
	 */
	public const string UNRESOLVABLE_RELATION_TARGET_SOURCE = 'relation-target-unresolvable-source';

	public function __construct(
		public ?PropertyName $propertyName,
		public string $code,
		public array $args = [],
		public ?int $valuePartIndex = null,
		public Severity $severity = Severity::Warning,
	) {
	}

	public function withPropertyName( PropertyName $name ): self {
		return new self(
			propertyName: $name,
			code: $this->code,
			args: $this->args,
			valuePartIndex: $this->valuePartIndex,
			severity: $this->severity,
		);
	}

	/**
	 * Whether this Violation should block writes under enforcement (ADR 26).
	 */
	public function isBlocking(): bool {
		return $this->severity === Severity::Error;
	}

	/**
	 * Whether this Violation blocks a write whatever the wiki's validation enforcement is set to.
	 * Enforcement governs how strictly a wiki holds its data to its Schemas; a Relation into a Source
	 * this wiki cannot reach is not a Schema question but a reference nothing can resolve, so it is
	 * refused either way.
	 */
	public function alwaysBlocksWrites(): bool {
		return $this->code === self::UNRESOLVABLE_RELATION_TARGET_SOURCE;
	}

}
