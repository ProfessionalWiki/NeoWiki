<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Search;

use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Makes one of MediaWiki's own database search engines index a page's Subjects along with its text.
 * A trait rather than a shared parent, because each engine has to extend the core one it stands in for.
 */
trait IndexesSubjectText {

	public function update( $id, $title, $text ): void {
		parent::update( $id, $title, $this->textWithSubjects( (int)$id, (string)$text ) );
	}

	private function textWithSubjects( int $pageId, string $text ): string {
		$subjectText = NeoWikiExtension::getInstance()->newSubjectSearchTextLookup()->getSearchTextForPage( $pageId );

		if ( $subjectText === '' ) {
			return $text;
		}

		// The page text arrives folded to lower case and normalized, and SQLite indexes what it is handed.
		$folded = MediaWikiServices::getInstance()->getContentLanguage()->lc( $subjectText );

		return $text . "\n" . $this->normalizeText( $folded );
	}

}
