<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatRegistry;

class NeoWikiExtension {

	private ValueFormatRegistry $formatRegistry;

	public static function getInstance(): self {
		/** @var ?NeoWikiExtension $instance */
		static $instance = null;
		$instance ??= new self();
		return $instance;
	}

	public function getFormatRegistry(): ValueFormatRegistry {
		if ( !isset( $this->formatRegistry ) ) {
			$this->formatRegistry = ValueFormatRegistry::withCoreFormats();
		}

		return $this->formatRegistry;
	}

}
