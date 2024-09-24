<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki;

readonly class NeoWikiConfig {

	public function __construct(
		public bool $enableDevelopmentUIs,
		public string $neo4jInternalWriteUrl,
		public string $neo4jInternalReadUrl,
	) {
	}

}
