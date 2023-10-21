<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;

class StubGuidGenerator implements GuidGenerator {

	public function __construct(
		private readonly string $guid
	) {
	}

	public function generate(): string {
		return $this->guid;
	}

}
