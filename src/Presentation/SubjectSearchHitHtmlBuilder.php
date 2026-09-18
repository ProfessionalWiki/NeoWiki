<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MessageLocalizer;
use ProfessionalWiki\NeoWiki\Application\Search\MatchedSearchLine;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchLanding;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchMatch;

/**
 * What a search result row shows of the Subject it matched. Matches are marked up the way
 * MediaWiki marks up the ones it finds in page text, so a Subject value and a paragraph highlight
 * alike.
 */
readonly class SubjectSearchHitHtmlBuilder {

	/**
	 * As much of a value as MediaWiki shows around a match it found in page text
	 * ({@see \SearchHighlighter::DEFAULT_CONTEXT_CHARS}), so a Subject holding a page of prose does
	 * not put it all in the results.
	 */
	private const int CONTEXT_CHARS = 75;

	public function __construct(
		private MessageLocalizer $messageLocalizer,
		private Language $language
	) {
	}

	/**
	 * The text of the row's link when it leads to a Subject rather than to a page. Plain text: the
	 * caller hands it to the link renderer, which escapes it.
	 */
	public function buildLinkText( SubjectSearchLanding $landing ): string {
		return SubjectNameMessage::from(
			$this->messageLocalizer,
			$landing->subjectName,
			$landing->subjectNameIsGenerated
		)->text();
	}

	public function buildExtract( SubjectSearchMatch $match ): string {
		$blocks = [ Html::element(
			'div',
			[ 'class' => 'ext-neowiki-searchresult-subject' ],
			$this->heading( $match )
		) ];

		foreach ( $match->lines as $line ) {
			$blocks[] = Html::rawElement(
				'div',
				[ 'class' => 'ext-neowiki-searchresult-statement' ],
				$this->lineHtml( $line )
			);
		}

		return Html::rawElement( 'div', [ 'class' => 'ext-neowiki-searchresult' ], implode( '', $blocks ) );
	}

	private function heading( SubjectSearchMatch $match ): string {
		if ( $match->subjectName === null ) {
			return $match->schemaName;
		}

		return $this->messageLocalizer->msg( 'neowiki-search-hit-subject' )
			->plaintextParams( $match->subjectName, $match->schemaName )->text();
	}

	private function lineHtml( MatchedSearchLine $line ): string {
		$value = '';
		$isMatch = false;

		foreach ( $this->aroundTheMatches( $line->parts ) as $part ) {
			$value .= $isMatch
				? Html::element( 'span', [ 'class' => 'searchmatch' ], $part )
				: htmlspecialchars( $part );
			$isMatch = !$isMatch;
		}

		if ( $line->propertyName === null ) {
			return $value;
		}

		return htmlspecialchars( $line->propertyName )
			. $this->messageLocalizer->msg( 'colon-separator' )->escaped()
			. $value;
	}

	/**
	 * The parts with everything before the first match and after the last one cut back to what fits
	 * a result row. The matches themselves and whatever sits between them are kept whole.
	 *
	 * @param string[] $parts
	 * @return string[]
	 */
	private function aroundTheMatches( array $parts ): array {
		$last = count( $parts ) - 1;

		$parts[0] = $this->language->truncateForVisual( $parts[0], -self::CONTEXT_CHARS, '...', false );
		$parts[$last] = $this->language->truncateForVisual( $parts[$last], self::CONTEXT_CHARS, '...', false );

		return $parts;
	}

}
