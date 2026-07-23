<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Resolves a Subject to the page that hosts it, gated by read access: the hosting page's identifiers
 * when the Subject is in the graph and the caller may read its page, and null for every reason it
 * cannot be served — the Subject is not in the graph, or its hosting page is unreadable or gone (an
 * unresolvable page id has nothing to authorize, so the authorizer denies it).
 *
 * The concept-URI negotiator ({@see \ProfessionalWiki\NeoWiki\EntryPoints\REST\ResolveSubjectIriApi})
 * uses this to decide its one indistinguishable not-found, byte identical whether the Subject is absent
 * or on a page the caller cannot read, so a harvested Subject id cannot be probed for existence (#1046).
 * This is the resolve→authorize gate of {@see RdfSubjectExporter} without loading page content: the
 * negotiator only needs the hosting page to redirect to, not the Subject's triples.
 */
readonly class SubjectHostingPageResolver {

	public function __construct(
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function resolveReadableHostingPage( SubjectId $subjectId ): ?PageIdentifiers {
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
