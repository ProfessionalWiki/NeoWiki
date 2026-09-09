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
		$tier = MediaWikiServices::getInstance()->getPermissionManager()
			->userHasRight( $user, 'apihighlimits' )
				? 'expensive'
				: 'default';

		return self::forTier( $tier );
	}

	/**
	 * The tier for parse-time queries. Parse output is shared through the parser cache, so the
	 * limits it was produced under must not depend on who happened to parse.
	 */
	public static function defaultTier(): self {
		return self::forTier( 'default' );
	}

	private static function forTier( string $tier ): self {
		/** @var array<string, array{timeoutSeconds: int, maxRows: int}> $config */
		$config = MediaWikiServices::getInstance()->getMainConfig()->get( 'NeoWikiQueryLimits' );

		return new self(
			timeoutSeconds: (int)$config[$tier]['timeoutSeconds'],
			maxRows: (int)$config[$tier]['maxRows'],
		);
	}

}
