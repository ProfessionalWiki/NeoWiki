<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\ResponseInterface;

/**
 * Marks a REST endpoint as read-only: it needs no write access, and its response must not be stored
 * by a shared cache.
 *
 * What these endpoints return depends on what the caller may read (#1046), and MediaWiki's REST layer
 * sends no `Vary: Cookie`, so a shared cache keying on the URL alone may serve one caller's response
 * to another. {@see \MediaWiki\Rest\Handler::applyCacheControl} closes that only for a persistent
 * session; a cookieless anonymous GET gets no header at all, so the fallback below repeats core's
 * string for it. The price is that anonymous RDF exports stop being CDN-cacheable.
 *
 * Core applies this only to what `execute()` returns: a 403 from the basic authorizer, a 304 from
 * `checkPreconditions()`, and any thrown error escape it. `must-revalidate` stays inert until a
 * handler offers an `ETag` or `Last-Modified` (#1212).
 *
 * Declaring write access instead would make {@see \MediaWiki\Rest\CorsUtils::authorize} reject
 * anonymous cross-origin requests from unlisted origins.
 *
 * Used only by {@see \MediaWiki\Rest\Handler} subclasses, whose applyCacheControl() it extends; a
 * handler needing something stricter overrides it, as {@see GetSubjectEditNoticesApi} does.
 */
trait ReadOnlyEndpoint {

	public function needsWriteAccess(): bool {
		return false;
	}

	public function applyCacheControl( ResponseInterface $response ): void {
		parent::applyCacheControl( $response );

		if ( $response->getHeaderLine( 'Cache-Control' ) === '' ) {
			$response->setHeader( 'Cache-Control', 'private,must-revalidate,s-maxage=0' );
		}
	}

}
