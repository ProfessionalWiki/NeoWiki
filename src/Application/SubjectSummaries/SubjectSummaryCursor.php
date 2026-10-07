<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * The position of a row in a Subject listing: its value in the sort and its Subject id, which breaks ties.
 * Only values every backend has, so a listing can move to another backend without changing the contract.
 */
readonly class SubjectSummaryCursor {

	public function __construct(
		public SubjectSummarySort $sort,
		public SortDirection $direction,
		public string|int|null $sortValue,
		public string $subjectId,
	) {
	}

	/**
	 * Opaque to clients, who hand it back unread.
	 */
	public function encode(): string {
		$json = json_encode( [ $this->sort->value, $this->direction->value, $this->sortValue, $this->subjectId ] );

		return rtrim( strtr( base64_encode( (string)$json ), '+/', '-_' ), '=' );
	}

	/**
	 * @throws InvalidSubjectSummaryCursorException
	 */
	public static function decode( string $encoded, SubjectSummarySort $sort, SortDirection $direction ): self {
		$json = base64_decode( strtr( $encoded, '-_', '+/' ), true );
		$fields = $json === false ? null : json_decode( $json, true );

		if ( !is_array( $fields ) || !array_is_list( $fields ) || count( $fields ) !== 4 ) {
			throw new InvalidSubjectSummaryCursorException();
		}

		[ $sortName, $directionName, $sortValue, $subjectId ] = $fields;

		if ( $sortName !== $sort->value
			|| $directionName !== $direction->value
			|| !self::fitsSort( $sortValue, $sort )
			|| !is_string( $subjectId )
			|| !SubjectId::isValidLocalId( $subjectId )
		) {
			throw new InvalidSubjectSummaryCursorException();
		}

		return new self( $sort, $direction, $sortValue, $subjectId );
	}

	/**
	 * @phpstan-assert-if-true string|int|null $sortValue
	 */
	private static function fitsSort( mixed $sortValue, SubjectSummarySort $sort ): bool {
		return match ( $sort ) {
			SubjectSummarySort::Newest => $sortValue === null,
			SubjectSummarySort::Edited => is_int( $sortValue ),
			SubjectSummarySort::Name, SubjectSummarySort::Schema, SubjectSummarySort::Page =>
				is_string( $sortValue ) || $sortValue === null,
		};
	}

}
