<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Closure;
use MediaWiki\Parser\Parser;

/**
 * Marks the pages that call a NeoWiki parser function or Lua function with a MediaWiki page property, which
 * Special:PagesWithProp lists: the wikitext that stops working once NeoWiki is removed.
 */
class FunctionCallTracking {

	public const string PAGE_PROP = 'neowiki_functions';

	public static function markPage( Parser $parser ): void {
		$parser->getOutput()->setUnsortedPageProperty( self::PAGE_PROP );
	}

	/**
	 * Every call counts, including one that fails or renders nothing: the page holds the call either way.
	 */
	public static function trackingCallsOf( callable $parserFunction ): Closure {
		return static function ( Parser $parser, string ...$args ) use ( $parserFunction ): string|array {
			self::markPage( $parser );
			return $parserFunction( $parser, ...$args );
		};
	}

}
