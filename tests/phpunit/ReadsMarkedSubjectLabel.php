<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use DOMDocument;
use DOMXPath;

/**
 * Reads the part of a Subject heading that the page replaces when the Subject gets a new label.
 */
trait ReadsMarkedSubjectLabel {

	/**
	 * The marked part's text, or null where the heading marks none.
	 */
	private static function markedSubjectLabel( string $html ): ?string {
		$document = new DOMDocument();
		$document->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING );

		$marked = ( new DOMXPath( $document ) )->query(
			'//*[contains(concat(" ", normalize-space(@class), " "), " ext-neowiki-subject-label ")]'
		);

		return $marked === false ? null : $marked->item( 0 )?->textContent;
	}

}
