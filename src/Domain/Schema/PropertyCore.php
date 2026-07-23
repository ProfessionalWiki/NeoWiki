<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;

readonly class PropertyCore {

	/**
	 * A null default means there is no default.
	 *
	 * @param array<string, Severity> $constraintSeverities Per-Constraint severity, keyed by
	 *   the Constraint's JSON key (e.g. 'maximum', 'required'). Populated generically at the
	 *   JSON boundary; read via PropertyDefinition::severityOf(). See ADR 26.
	 */
	public function __construct(
		public string $description,
		public bool $required,
		public mixed $default,
		public array $constraintSeverities = [],
	) {
	}

}
