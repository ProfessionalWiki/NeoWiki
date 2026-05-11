<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Query;

readonly class QueryLimits {

	public function __construct(
		public int $timeoutSeconds,
		public int $maxRows,
	) {
	}

}
