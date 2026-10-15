<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

/**
 * The rows to delete and to insert to turn the rows a table holds for a page into the ones it should hold. A
 * row whose values changed is in both, and a row that is right already in neither.
 */
readonly class RowChanges {

	/**
	 * @param array<array-key, array<string, mixed>> $deletions
	 * @param array<array-key, array<string, mixed>> $insertions
	 */
	private function __construct(
		public array $deletions,
		public array $insertions,
	) {
	}

	/**
	 * Rows are compared value for value and type for type, so both sides have to be built alike.
	 *
	 * @param array<array-key, array<string, mixed>> $stored The rows the table holds, by primary key
	 * @param array<array-key, array<string, mixed>> $wanted The rows it should hold, by primary key
	 */
	public static function between( array $stored, array $wanted ): self {
		return new self(
			deletions: self::rowsMissingFrom( $wanted, $stored ),
			insertions: self::rowsMissingFrom( $stored, $wanted ),
		);
	}

	/**
	 * @param array<array-key, array<string, mixed>> $rows
	 * @param array<array-key, array<string, mixed>> $candidates
	 * @return array<array-key, array<string, mixed>> The candidates that $rows does not hold as they are
	 */
	private static function rowsMissingFrom( array $rows, array $candidates ): array {
		return array_filter(
			$candidates,
			static fn ( array $candidate, int|string $key ): bool => ( $rows[$key] ?? null ) !== $candidate,
			ARRAY_FILTER_USE_BOTH
		);
	}

	public function isEmpty(): bool {
		return $this->deletions === [] && $this->insertions === [];
	}

}
