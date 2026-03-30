<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

/**
 * Marker type for datetime values in page properties.
 * Graph database backends recognize this type and store it
 * using their native datetime representation.
 */
readonly class PageDateTime {

	/**
	 * @param string $timestamp In the standard MediaWiki format, ie 20230726163439
	 */
	public function __construct(
		public string $timestamp,
	) {
	}

}
