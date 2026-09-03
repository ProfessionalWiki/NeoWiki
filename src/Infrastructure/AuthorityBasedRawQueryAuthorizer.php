<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Application\RawQueryAuthorizer;

class AuthorityBasedRawQueryAuthorizer implements RawQueryAuthorizer {

	public const string RIGHT = 'neowiki-query';

	public function __construct(
		private readonly Authority $authority,
	) {
	}

	public function authorizeRawQuery(): bool {
		return $this->authority->isAllowed( self::RIGHT );
	}

}
