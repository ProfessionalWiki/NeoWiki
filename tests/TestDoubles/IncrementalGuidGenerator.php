<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;

class IncrementalGuidGenerator implements GuidGenerator {

	private int $currentIndex;

	public function __construct( int $startingIndex = 0 ) {
		$this->currentIndex = $startingIndex;
	}

	public function generate(): string {
		return '00000000-0000-0000-0000-' . sprintf( '%012d', $this->currentIndex++ );
	}

}
