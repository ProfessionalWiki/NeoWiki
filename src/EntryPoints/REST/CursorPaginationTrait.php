<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\ResponseException;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Cursor pagination for the listing endpoints over page-backed content (Schemas, Layouts,
 * Mappings). The cursor is opaque to clients and holds the key of the last listed page, its page
 * ID or, in a listing by name, its name; the page is filled from an iterable of readable names
 * under those keys. Because unreadable pages are never yielded, they neither take page space nor surface in
 * the cursor, and no total is reported, so a caller cannot infer the existence or count of
 * read-restricted pages from the pagination (#1062).
 */
trait CursorPaginationTrait {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function paginationParamSettings(): array {
		return [
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
				self::PARAM_DESCRIPTION => 'Maximum number of items to return.',
			],
			'cursor' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => null,
				self::PARAM_DESCRIPTION => 'Opaque pagination cursor: the nextCursor of the previous response. Omit for the first page.',
			],
		];
	}

	private function pageIdFromCursor( ?string $cursor ): int {
		if ( $cursor === null || $cursor === '' ) {
			return 0;
		}

		if ( !ctype_digit( $cursor ) ) {
			throw $this->invalidCursor();
		}

		return (int)$cursor;
	}

	/**
	 * Encodes the name a listing by name continues after, so clients pass it on unread.
	 */
	private function cursorForName( string $name ): string {
		return rtrim( strtr( base64_encode( json_encode( $name, JSON_THROW_ON_ERROR ) ), '+/', '-_' ), '=' );
	}

	private function nameFromCursor( ?string $cursor ): string {
		if ( $cursor === null || $cursor === '' ) {
			return '';
		}

		$json = base64_decode( strtr( $cursor, '-_', '+/' ), true );
		$name = $json === false ? null : json_decode( $json );

		if ( !is_string( $name ) ) {
			throw $this->invalidCursor();
		}

		return $name;
	}

	private function invalidCursor(): ResponseException {
		// The structured body matches the other handlers' createHttpError responses.
		return new ResponseException(
			$this->getResponseFactory()->createHttpError( 400, [ 'message' => 'Invalid cursor' ] )
		);
	}

	/**
	 * Fills a page with up to $limit summaries and derives the follow-up cursor. The cursor is the
	 * key of the last served item: a name skipped mid-page (readable but malformed) sorts below it
	 * and is not revisited, while a malformed name after it is scanned and skipped afresh by the
	 * next page. nextCursor is null exactly when the listing is exhausted.
	 *
	 * @param iterable<int|string, mixed> $readableNames
	 * @param callable(mixed): ?array $loadSummary null when the name does not resolve to a listable item
	 * @return array{items: list<array>, nextCursor: ?string}
	 */
	private function buildPage( iterable $readableNames, int $limit, callable $loadSummary ): array {
		$items = [];
		$lastKey = 0;
		$hasMore = false;

		foreach ( $readableNames as $key => $name ) {
			if ( count( $items ) === $limit ) {
				$hasMore = true;
				break;
			}

			$lastKey = $key;
			$summary = $loadSummary( $name );

			if ( $summary === null ) {
				continue;
			}

			$items[] = $summary;
		}

		return [
			'items' => $items,
			'nextCursor' => $hasMore ? (string)$lastKey : null,
		];
	}

}
