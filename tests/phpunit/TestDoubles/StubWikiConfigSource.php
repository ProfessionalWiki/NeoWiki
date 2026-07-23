<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\WikiConfig\WikiConfigSource;

class StubWikiConfigSource implements WikiConfigSource {

	public int $readCount = 0;

	public function __construct(
		private readonly ?array $configData
	) {
	}

	public function readConfig(): ?array {
		$this->readCount++;
		return $this->configData;
	}

}
