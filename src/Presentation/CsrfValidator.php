<?php

namespace ProfessionalWiki\NeoWiki\MediaWiki\Presentation;

use MediaWiki\Rest\HttpException;
use MediaWiki\Session\CsrfTokenSet;

class CsrfValidator {

	public function __construct(
		private readonly \WebRequest $request,
		private readonly CsrfTokenSet $csrfTokenSet
	) {
	}

	public function verifyCsrfToken(): bool {
		$tokens = $this->request->getHeader( 'X-CSRF-TOKEN' );
		$token = is_array( $tokens ) ? $tokens[ 0 ] : $tokens;

		if ( !$token || !$this->csrfTokenSet->matchToken( $token ) ) {
			throw new HttpException(
				'This endpoint requires a CSRF token',
				403
			);
		}
		return true;
	}
}
