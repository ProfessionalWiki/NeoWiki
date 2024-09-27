<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;

class StubIdGenerator implements IdGenerator {

	public function __construct(
		private readonly string $id
	) {
	}

	public function generate(): string {
		return $this->id;
	}

}
