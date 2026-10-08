<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\SubjectCountLookup;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedRawQueryAuthorizer;

class GetSubjectCountsApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function __construct(
		private readonly SubjectCountLookup $lookup,
	) {
	}

	public function run(): Response {
		// The counts read the whole store, Subjects on pages the caller may not read included, as a raw query does.
		if ( !$this->getAuthority()->isAllowed( AuthorityBasedRawQueryAuthorizer::RIGHT ) ) {
			return $this->permissionDenied();
		}

		// An object, so no counts encode as {} rather than [].
		return $this->getResponseFactory()->createJson( [
			'counts' => (object)$this->lookup->getSubjectCountsBySchema(),
		] );
	}

	/**
	 * What the query endpoints answer a caller without the right.
	 */
	private function permissionDenied(): Response {
		$response = $this->getResponseFactory()->createJson( [
			'errorType' => 'permissionDenied',
			'message' => 'You do not have permission to run queries.',
		] );
		$response->setStatus( 403 );

		return $response;
	}

}
