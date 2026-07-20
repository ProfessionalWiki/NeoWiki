<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;

readonly class Violation {

	/**
	 * System conditions rather than user-correctable Constraints: the wiki, not the edit,
	 * is in a degraded state. They are reported but never reject a write. These are the
	 * `warning` tier that ADR 26 will make configurable per Constraint.
	 */
	private const NON_BLOCKING_CODES = [ 'schema-not-found', 'unregistered-type', 'relation-target-not-found' ];

	public function __construct(
		public ?PropertyName $propertyName,
		public string $code,
		public array $args = [],
		public ?int $valuePartIndex = null,
	) {
	}

	public function withPropertyName( PropertyName $name ): self {
		return new self(
			propertyName: $name,
			code: $this->code,
			args: $this->args,
			valuePartIndex: $this->valuePartIndex,
		);
	}

	/**
	 * Whether this Violation should block writes under enforcement.
	 */
	public function isBlocking(): bool {
		return !in_array( $this->code, self::NON_BLOCKING_CODES, true );
	}

}
