<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
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
 *   - else (a browser's `text/html`, `*&#47;*`, an absent or unrecognized `Accept`) → `Special:Subject` for
 *     that Subject, or the page it is stored on when `$wgNeoWikiDereferenceSubjectsToHostingPage` is set.
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

	use ReadOnlyEndpoint;

	public function run( string $subjectId ): Response {
		$extension = NeoWikiExtension::getInstance();
		$id = $extension->getSubjectIdParser()->parse( $subjectId );

		if ( $id === null ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Invalid Subject ID: ' . $subjectId,
			] );
		}

		$hostingPage = $extension
			->newSubjectHostingPageResolver( $this->getAuthority() )
			->resolveReadableHostingPage( $id );

		if ( $hostingPage === null ) {
			return $this->noDataResponse( $subjectId );
		}

		$rdfFormat = $this->negotiatedRdfFormat();

		if ( $rdfFormat !== null ) {
			return $this->negotiatedRedirect( $this->subjectRdfUrl( $id->text, $rdfFormat ) );
		}

		$htmlUrl = $this->htmlUrl( $id, $hostingPage );

		if ( $htmlUrl === null ) {
			return $this->noDataResponse( $subjectId );
		}

		return $this->negotiatedRedirect( $htmlUrl );
	}

	/**
	 * The RDF serialization the Accept header asks for, or null when it does not ask for RDF (so the
	 * dereference lands on an HTML view).
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

	/**
	 * The Subject's own view, or the page it is stored on when the wiki asks for that. The hosting page
	 * is authorized either way: it is what the resolver above gated on.
	 *
	 * The resolver already authorized that page, so it resolves here; a null only means it was deleted
	 * within this same request, which takes the same not-found as any other unservable Subject.
	 */
	private function htmlUrl( SubjectId $id, PageIdentifiers $hostingPage ): ?string {
		if ( !$this->dereferenceToHostingPage() ) {
			return SpecialPage::getTitleFor( 'Subject', $id->text )->getCanonicalURL();
		}

		return MediaWikiServices::getInstance()->getTitleFactory()
			->newFromID( $hostingPage->getId()->id )?->getCanonicalURL();
	}

	/**
	 * Where a redirect points is chosen from the `Accept` header, so a cache must key on it. The 400
	 * and 404 replies are the same for every representation and carry no Vary.
	 */
	private function negotiatedRedirect( string $url ): Response {
		$response = $this->getResponseFactory()->createSeeOther( $url );
		$response->addHeader( 'Vary', 'Accept' );

		return $response;
	}

	private function dereferenceToHostingPage(): bool {
		// The effective flag combines the MediaWiki:NeoWiki page with $wgNeoWikiDereferenceSubjectsToHostingPage
		// (the page wins when it sets a valid boolean; an invalid page value has already fallen back).
		return NeoWikiExtension::getInstance()->dereferenceSubjectsToHostingPage();
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
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject whose concept URI to dereference: 15 characters starting with "s" for a Subject of this wiki, or "sourceKey:localId" for one from another Source.',
			],
		];
	}

}
