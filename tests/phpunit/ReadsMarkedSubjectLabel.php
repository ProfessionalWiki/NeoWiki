<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * Reads the part of a Subject heading that the page replaces when the Subject gets a new label.
 */
trait ReadsMarkedSubjectLabel {

	/**
	 * The marked part's text, or null where the heading marks none.
	 */
	private static function markedSubjectLabel( string $html ): ?string {
		return DOMCompat::querySelector( DOMUtils::parseHTML( $html ), '.ext-neowiki-subject-label' )?->textContent;
	}

}
