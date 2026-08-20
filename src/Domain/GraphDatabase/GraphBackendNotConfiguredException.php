<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

use RuntimeException;

/**
 * Thrown when code that needs a graph database backend is reached on a wiki that has none configured.
 *
 * A wiki with no backend is a supported mode: the structured-data features work without one, and the
 * query surfaces a backend brings are simply not registered (ADR 32). Reaching this means a caller got
 * past that gating. It is a catchable, expected-runtime-state signal (unlike the LogicException guards
 * on genuinely gated surfaces), so degradation boundaries can turn it into a clear notice.
 */
class GraphBackendNotConfiguredException extends RuntimeException {

	public function __construct(
		string $message = 'This feature needs a graph database backend, and this wiki has none configured.'
	) {
		parent::__construct( $message );
	}

}
