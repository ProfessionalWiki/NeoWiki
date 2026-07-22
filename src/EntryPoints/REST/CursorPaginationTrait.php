<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\ResponseException;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Cursor pagination for the listing endpoints over page-backed content (Schemas, Layouts,
 * Mappings). The cursor is opaque to clients and holds the page ID of the last listed page; the
 * page is filled from an iterable of readable names keyed by page ID. Because unreadable pages are
 * never yielded, they neither take page space nor surface in the cursor, and no total is reported,
 * so a caller cannot infer the existence or count of read-restricted pages from the pagination
 * (#1062).
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
			// The structured body matches the other handlers' createHttpError responses.
			throw new ResponseException(
				$this->getResponseFactory()->createHttpError( 400, [ 'message' => 'Invalid cursor' ] )
			);
		}

		return (int)$cursor;
	}

	/**
	 * Fills a page with up to $limit summaries and derives the follow-up cursor. The cursor is the
	 * page ID of the last consumed name, so names a load skips (readable but malformed) are not
	 * re-consumed by the next page. nextCursor is null exactly when the listing is exhausted.
	 *
	 * @param iterable<int, mixed> $readableNames
	 * @param callable(mixed): ?array $loadSummary null when the name does not resolve to a listable item
	 * @return array{items: list<array>, nextCursor: ?string}
	 */
	private function buildPage( iterable $readableNames, int $limit, callable $loadSummary ): array {
		$items = [];
		$lastPageId = 0;
		$hasMore = false;

		foreach ( $readableNames as $pageId => $name ) {
			if ( count( $items ) === $limit ) {
				$hasMore = true;
				break;
			}

			$lastPageId = $pageId;
			$summary = $loadSummary( $name );

			if ( $summary === null ) {
				continue;
			}

			$items[] = $summary;
		}

		return [
			'items' => $items,
			'nextCursor' => $hasMore ? (string)$lastPageId : null,
		];
	}

}
