<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * What a page's Subjects add to its search result row.
 */
readonly class SubjectSearchHit {

	/**
	 * @param ?SubjectSearchLanding $landing Null keeps the page as the row's destination
	 * @param ?SubjectSearchMatch $match Null when no Subject could be attributed
	 */
	public function __construct(
		public ?SubjectSearchLanding $landing,
		public ?SubjectSearchMatch $match
	) {
	}

}
