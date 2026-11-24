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
		public ?string $replacedSetting,
	) {
	}

	/**
	 * A feature outside the upgrade promise: off unless a wiki turns it on, and any release may change or
	 * remove it without migrating the data it wrote.
	 */
	public static function experimental( string $name ): self {
		return new self( $name, enabledByDefault: false, replacedSetting: null );
	}

	/**
	 * @param string|null $replacedSetting The setting, named without $wg, that switched this part of NeoWiki
	 *  before it became a feature. While a wiki sets it away from the feature's default, it decides the feature
	 *  and reports itself deprecated.
	 */
	public static function stable( string $name, bool $enabledByDefault, ?string $replacedSetting = null ): self {
		return new self( $name, $enabledByDefault, $replacedSetting );
	}

}
