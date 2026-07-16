<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application;

readonly class SparqlQueryRequest {

	public function __construct(
		public string $sparql,
		public SparqlQueryLimits $limits,
	) {
	}

}
