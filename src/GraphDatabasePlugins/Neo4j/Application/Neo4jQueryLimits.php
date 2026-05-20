<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

readonly class Neo4jQueryLimits {

	public function __construct(
		public int $timeoutSeconds,
		public int $maxRows,
	) {
	}

	public static function forUser( User $user ): self {
		/** @var array<string, array{timeoutSeconds: int, maxRows: int}> $config */
		$config = MediaWikiServices::getInstance()->getMainConfig()->get( 'NeoWikiQueryLimits' );

		$tier = MediaWikiServices::getInstance()->getPermissionManager()
			->userHasRight( $user, 'apihighlimits' )
				? 'expensive'
				: 'default';

		return new self(
			timeoutSeconds: (int)$config[$tier]['timeoutSeconds'],
			maxRows: (int)$config[$tier]['maxRows'],
		);
	}

}
