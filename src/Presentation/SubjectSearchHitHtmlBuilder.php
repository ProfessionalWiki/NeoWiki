<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MessageLocalizer;
use ProfessionalWiki\NeoWiki\Application\Search\MatchedSearchLine;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchMatch;

/**
 * The Subject block of a search result row, with matches marked up like MediaWiki's own.
 */
readonly class SubjectSearchHitHtmlBuilder {

	/**
	 * What MediaWiki keeps around a match in page text ({@see \SearchHighlighter::DEFAULT_CONTEXT_CHARS}).
	 */
	private const int CONTEXT_CHARS = 75;

	public function __construct(
		private MessageLocalizer $messageLocalizer,
		private Language $language
	) {
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
	 * Cuts the text before the first match and after the last; the matches and what lies between
	 * them stay whole.
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
