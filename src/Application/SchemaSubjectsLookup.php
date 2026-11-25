<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

/**
 * The Subjects pages publish with one Schema, in the order of their ids, each with the page holding it.
 */
interface SchemaSubjectsLookup {

	/**
	 * Up to $limit Subjects after the given Subject on the given page. A Subject held by more than one page
	 * comes once per page, in page id order.
	 *
	 * @return list<array{subjectId: string, pageId: int}>
	 */
	public function getSubjectsAfter( string $schemaName, string $afterSubjectId, int $afterPageId, int $limit ): array;

}
