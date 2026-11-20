<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Content\ContentHandler;
use MediaWiki\OutputTransform\OutputTransformPipeline;
use MediaWiki\Parser\Parser;

/**
 * Reads the call stack because MediaWiki has no way to ask whether a parse is under way: the parser
 * keeps that to itself, and what a special page transcluded into a page renders reaches hooks as if
 * no parse were running. What a parse renders can land in the parser cache, which serves it to every
 * reader of the page, and so can what the transforms after it render from MediaWiki 1.47 on.
 */
class CallStack {

	public static function isInsideParse(): bool {
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
			if ( isset( $frame['class'] ) && self::isParseFrame( $frame['class'], $frame['function'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Content models other than wikitext render without the parser.
	 */
	private static function isParseFrame( string $class, string $function ): bool {
		return is_a( $class, Parser::class, true )
			|| is_a( $class, OutputTransformPipeline::class, true )
			|| ( is_a( $class, ContentHandler::class, true ) && $function === 'getParserOutput' );
	}

}
