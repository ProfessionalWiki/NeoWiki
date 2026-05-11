<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Query;

readonly class QueryRequest {

	/**
	 * @param array<string,mixed> $parameters
	 */
	public function __construct(
		public string $cypher,
		public array $parameters,
		public QueryLimits $limits,
	) {
	}

}
