<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

/**
 * A part of NeoWiki that a wiki can turn on or off with $wgNeoWikiFeatures.
 */
readonly class Feature {

	private function __construct(
		public string $name,
		public bool $enabledByDefault,
	) {
	}

	/**
	 * A feature outside the upgrade promise: off unless a wiki turns it on, and any release may change or
	 * remove it without migrating the data it wrote.
	 */
	public static function experimental( string $name ): self {
		return new self( $name, enabledByDefault: false );
	}

	public static function stable( string $name, bool $enabledByDefault ): self {
		return new self( $name, $enabledByDefault );
	}

}
