<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\GraphRebuild\ProjectionChangeTimeLookup;

class InMemoryProjectionChangeTimeLookup implements ProjectionChangeTimeLookup {

	/**
	 * @param array<string, ?string> $changeTimes Keys are projection names
	 */
	public function __construct(
		private readonly array $changeTimes,
	) {
	}

	public function getLastChangeTime( string $projection ): ?string {
		return $this->changeTimes[$projection] ?? null;
	}

}
