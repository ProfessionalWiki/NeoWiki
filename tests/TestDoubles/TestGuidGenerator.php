<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;

class TestGuidGenerator implements GuidGenerator {

	private int $currentIndex = 0;

	public function __construct( private readonly string|array $nextGuid ) {
	}

	public function generate(): string {
		if ( !is_array( $this->nextGuid ) ) {
			return $this->nextGuid;
		}

		$guid = $this->nextGuid[ $this->currentIndex ];
		$this->currentIndex++;

		return $guid;
	}

}
