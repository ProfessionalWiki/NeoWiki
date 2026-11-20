<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Application\SchemaSubjectsLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Checks again the pages publishing Subjects of one Schema, a batch at a time, as after the Schema changed.
 */
readonly class SchemaViolationChecker {

	public function __construct(
		private SchemaSubjectsLookup $schemaSubjectsLookup,
		private PageViolationChecker $pageChecker,
	) {
	}

	/**
	 * Checks the pages holding up to $limit Subjects of the Schema after the given Subject on the given page.
	 *
	 * @return array{subjectId: string, pageId: int}|null The Subject and page to continue after, null when no
	 *   Subjects remain
	 */
	public function checkBatch( string $schemaName, string $afterSubjectId, int $afterPageId, int $limit ): ?array {
		$subjects = $this->schemaSubjectsLookup->getSubjectsAfter( $schemaName, $afterSubjectId, $afterPageId, $limit );

		foreach ( array_unique( array_column( $subjects, 'pageId' ) ) as $pageId ) {
			$this->pageChecker->checkPage( new PageId( $pageId ) );
		}

		return count( $subjects ) < $limit ? null : $subjects[array_key_last( $subjects )];
	}

}
