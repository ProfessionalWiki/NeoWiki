<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;

class TestGuidGenerator implements GuidGenerator {

	public function __construct( private readonly string $nextGuid ) {
	}

	public function generate(): string {
		return $this->nextGuid;
	}

}
