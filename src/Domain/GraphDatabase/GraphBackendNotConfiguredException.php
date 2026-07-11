<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

use RuntimeException;

/**
 * Thrown when code that needs a graph database backend is reached on a wiki that has none configured.
 *
 * NeoWiki requires a configured graph backend to provide its structured-data features; a wiki with no
 * backend is a misconfiguration, not a supported operating mode (see ADR 019, which defers full
 * no-backend operation). This is a catchable, expected-runtime-state signal (unlike the LogicException
 * guards on genuinely gated surfaces), so degradation boundaries can turn it into a clear notice.
 */
class GraphBackendNotConfiguredException extends RuntimeException {

	public function __construct(
		string $message = 'NeoWiki requires a configured graph database backend. Configure the Neo4j read and write Bolt URLs.'
	) {
		parent::__construct( $message );
	}

}
