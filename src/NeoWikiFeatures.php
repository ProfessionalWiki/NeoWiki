<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

class NeoWikiFeatures {

	/**
	 * The features a wiki can turn on or off with $wgNeoWikiFeatures. List each one for admins, marking the
	 * experimental ones, under "Turning features on or off" in docs/operations/installation.md. A feature that
	 * becomes stable keeps its name, so a wiki that turned it on keeps it on.
	 *
	 * @return Feature[]
	 */
	public static function all(): array {
		return [];
	}

}
