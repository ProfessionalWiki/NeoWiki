<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

/**
 * Reads the part of a Subject heading that the page replaces when the Subject gets a new label.
 */
trait ReadsMarkedSubjectLabel {

	/**
	 * The marked part's text, or null where the heading marks none.
	 */
	private static function markedSubjectLabel( string $html ): ?string {
		$marked = preg_match(
			'/<span [^>]*class="(?:[^"]* )?ext-neowiki-subject-label(?: [^"]*)?"[^>]*>(.*?)<\/span>/',
			$html,
			$matches
		);

		return $marked === 1 ? html_entity_decode( strip_tags( $matches[1] ) ) : null;
	}

}
