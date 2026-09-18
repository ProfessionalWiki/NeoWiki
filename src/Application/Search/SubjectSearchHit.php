<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * What the Subjects of a page add to its row in a list of search results.
 */
readonly class SubjectSearchHit {

	/**
	 * @param ?SubjectSearchLanding $landing Where to send the reader instead of the page, or null to
	 *   leave the page as the row's destination
	 * @param ?SubjectSearchMatch $match What the row should show of the matched Subject, or null
	 *   when no Subject could be held responsible for the hit
	 */
	public function __construct(
		public ?SubjectSearchLanding $landing,
		public ?SubjectSearchMatch $match
	) {
	}

}
