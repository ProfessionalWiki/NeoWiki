<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\RawQueryAuthorizer;

/**
 * Authorizes or denies every raw query.
 */
class StubRawQueryAuthorizer implements RawQueryAuthorizer {

	public function __construct(
		private readonly bool $allowed
	) {
	}

	public function authorizeRawQuery(): bool {
		return $this->allowed;
	}

}
