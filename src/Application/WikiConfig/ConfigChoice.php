<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\WikiConfig;

/**
 * One value of a setting that the configuration form offers by name, as one option among the setting's others.
 */
readonly class ConfigChoice {

	public function __construct(
		public mixed $value,
		public string $labelMessage,
		public string $helpMessage,
	) {
	}

}
