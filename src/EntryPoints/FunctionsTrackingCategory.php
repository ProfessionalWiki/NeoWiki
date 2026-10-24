<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Closure;
use MediaWiki\Parser\Parser;

/**
 * Lists the pages that call a NeoWiki parser function or Lua function: the wikitext that stops working
 * once NeoWiki is removed.
 */
class FunctionsTrackingCategory {

	public const string MESSAGE_KEY = 'neowiki-functions-category';

	public static function addTo( Parser $parser ): void {
		$parser->addTrackingCategory( self::MESSAGE_KEY );
	}

	/**
	 * Every call counts, including one that fails or renders nothing: the page holds the call either way.
	 */
	public static function trackingCallsOf( callable $parserFunction ): Closure {
		return static function ( Parser $parser, string ...$args ) use ( $parserFunction ): string|array {
			self::addTo( $parser );
			return $parserFunction( $parser, ...$args );
		};
	}

}
