<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Resolves a Subject id to the page hosting it, gated on the caller's read access to that page.
 *
 * Answers the page's identifiers only for a local Subject the index names a page for, when the
 * caller may read that page. Every other case answers null, and callers present that null as their
 * not-found shape, so that the cases cannot be told apart:
 *
 * - A page the caller may not read is denied in the not-found shape PageReadAuthorizer prescribes.
 * - A local Subject no page hosts has nothing to authorize against: every right on a Subject is a
 *   right on the page holding it (ADR 32).
 * - A Subject id from another Source is never indexed (PageIdentifiersLookup), so it is answered
 *   without the query.
 *
 * Write actions resolve through this before their write check, so that a 403 cannot reveal what the
 * 404 hides (ADR 27).
 */
readonly class SubjectHostingPageResolver {

	public function __construct(
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function resolveReadableHostingPage( SubjectId $subjectId ): ?PageIdentifiers {
		if ( !$subjectId->isLocal() ) {
			return null;
		}

		$pageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId );

		if ( $pageIdentifiers === null ) {
			return null;
		}

		if ( !$this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() ) ) {
			return null;
		}

		return $pageIdentifiers;
	}

}
