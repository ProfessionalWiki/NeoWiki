<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use SearchEngine;

/**
 * A search engine that records what it is handed to index rather than writing it anywhere. MediaWiki
 * builds the engine itself, from the class name in $wgSearchType, so what it recorded is read back
 * statically.
 */
class RecordingSearchEngine extends SearchEngine {

	/**
	 * @var array<int, string> Keys are page ids
	 */
	private static array $indexedText = [];

	public static function forgetIndexedText(): void {
		self::$indexedText = [];
	}

	/**
	 * Null when the page was never indexed.
	 */
	public static function textIndexedForPage( int $pageId ): ?string {
		return self::$indexedText[$pageId] ?? null;
	}

	public function update( $id, $title, $text ): void {
		self::$indexedText[(int)$id] = (string)$text;
	}

}
