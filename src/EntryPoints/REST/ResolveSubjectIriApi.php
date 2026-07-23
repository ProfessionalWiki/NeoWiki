<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Dereferences a Subject's concept URI — `$wgNeoWikiRdfBaseUri/entity/{subjectId}`, the `neo-subj:`
 * IRI every RDF export mints — by content negotiation, replying 303 See Other with an absolute
 * Location:
 *
 *   - `Accept` includes `application/trig` → the Subject's TriG RDF export.
 *   - else `Accept` includes `text/turtle` → the Subject's Turtle RDF export (TriG wins a tie, matching
 *     {@see RdfFormatNegotiation}).
 *   - else (a browser's `text/html`, `*&#47;*`, an absent or unrecognized `Accept`) → the hosting page, or
 *     its Data tab row when `$wgNeoWikiDereferenceSubjectsToDataTab` is enabled.
 *
 * The RDF branches target the native projection: selecting an ontology target or a specific
 * serialization stays on the per-Subject RDF endpoint, keeping this concept-URI surface Accept-only.
 *
 * A Subject that is not in the graph or lives on a page the caller cannot read returns one
 * indistinguishable 404, byte identical to the per-Subject RDF endpoint (#1046), so the concept URI
 * cannot be used to probe for restricted Subjects. The Subject is resolved before the `Accept` header
 * is inspected, so an absent Subject answers that same 404 whatever representation it asked for.
 */
class ResolveSubjectIriApi extends SimpleHandler {

	public function run( string $subjectId ): Response {
		if ( !SubjectId::isValid( $subjectId ) ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Invalid Subject ID: ' . $subjectId,
			] );
		}

		$hostingPage = NeoWikiExtension::getInstance()
			->newSubjectHostingPageResolver( $this->getAuthority() )
			->resolveReadableHostingPage( new SubjectId( $subjectId ) );

		if ( $hostingPage === null ) {
			return $this->noDataResponse( $subjectId );
		}

		$rdfFormat = $this->negotiatedRdfFormat();

		if ( $rdfFormat !== null ) {
			return $this->getResponseFactory()->createSeeOther( $this->subjectRdfUrl( $subjectId, $rdfFormat ) );
		}

		return $this->hostingPageRedirect( $subjectId, $hostingPage );
	}

	/**
	 * The RDF serialization the Accept header asks for, or null when it does not ask for RDF (so the
	 * dereference lands on the hosting page).
	 */
	private function negotiatedRdfFormat(): ?string {
		$accept = $this->getRequest()->getHeaderLine( 'Accept' );

		// TriG is a superset of Turtle, so a client that lists both gets TriG (mirrors RdfFormatNegotiation).
		if ( str_contains( $accept, 'application/trig' ) ) {
			return 'trig';
		}

		if ( str_contains( $accept, 'text/turtle' ) ) {
			return 'turtle';
		}

		return null;
	}

	private function subjectRdfUrl( string $subjectId, string $format ): string {
		return $this->getRouter()->getRouteUrl(
			'/neowiki/v0/subject/{subjectId}/rdf',
			[ 'subjectId' => $subjectId ],
			[ 'format' => $format ]
		);
	}

	private function hostingPageRedirect( string $subjectId, PageIdentifiers $hostingPage ): Response {
		$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromID( $hostingPage->getId()->id );

		// The resolver already authorized this page, so it resolves here; a null only means the page was
		// deleted within this same request, which takes the same not-found as any other unservable Subject.
		if ( $title === null ) {
			return $this->noDataResponse( $subjectId );
		}

		return $this->getResponseFactory()->createSeeOther( $this->hostingPageUrl( $title, $subjectId ) );
	}

	private function hostingPageUrl( Title $title, string $subjectId ): string {
		if ( $this->dataTabDereference() ) {
			// The Data tab reads this fragment on mount to expand, scroll to, and highlight the row. The
			// fragment is the bare Subject id (like Wikibase's `#P123`), not the row's internal DOM id.
			return $title->getCanonicalURL( [ 'action' => SubjectsAction::ACTION_NAME ] ) . '#' . $subjectId;
		}

		return $title->getCanonicalURL();
	}

	private function dataTabDereference(): bool {
		// The effective flag combines the MediaWiki:NeoWiki page with $wgNeoWikiDereferenceSubjectsToDataTab
		// (the page wins when it sets a valid boolean; an invalid page value has already fallen back).
		return NeoWikiExtension::getInstance()->dereferenceSubjectsToDataTab();
	}

	private function noDataResponse( string $subjectId ): Response {
		return $this->getResponseFactory()->createHttpError( 404, [
			'message' => 'No NeoWiki data found for subject: ' . $subjectId,
		] );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject whose concept URI to dereference. 15 characters, starting with "s".',
			],
		];
	}

}
