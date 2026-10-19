<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

/**
 * Names enough of the pages to see what they have in common, and counts the rest: a run that fails on the whole
 * wiki must not bury its summary under page ids.
 */
class PageIdSummary {

	private const int NAMED_PAGES = 5;

	/**
	 * @param int[] $pageIds
	 */
	public static function of( array $pageIds ): string {
		$named = array_slice( $pageIds, 0, self::NAMED_PAGES );
		$rest = count( $pageIds ) - count( $named );

		return implode( ', ', $named ) . ( $rest === 0 ? '' : " and $rest more" );
	}

}
