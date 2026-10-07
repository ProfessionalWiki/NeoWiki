<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use ProfessionalWiki\NeoWiki\Application\Search\MatchedSearchLine;

/**
 * Renders what a matcher matched as one readable string, the matched stretches in square brackets:
 * `Museum in [Amsterdam] since 1800`. Tests then assert on what was matched rather than on how the
 * matcher represents it.
 */
trait MarkedMatchesTrait {

	/**
	 * @param string[] $parts As SearchTermMatcher::splitOnMatches() returns them
	 */
	private function marked( array $parts ): string {
		$text = '';

		foreach ( $parts as $index => $part ) {
			$text .= $index % 2 === 1 ? "[$part]" : $part;
		}

		return $text;
	}

	private function markedLine( MatchedSearchLine $line ): string {
		return $this->marked( $line->parts );
	}

}
