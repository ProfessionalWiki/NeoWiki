<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

/**
 * Per-tier resource cap for a SPARQL query, read from the shared `NeoWikiQueryLimits` config — the same
 * config the Neo4j surfaces use. Mirrors
 * {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits}, but carries
 * only `timeoutSeconds`: unlike Cypher, the SPARQL surfaces do not apply the `maxRows` cap (see
 * {@see SparqlQueryService} for why truncating the W3C results document is out of scope here).
 */
readonly class SparqlQueryLimits {

	public function __construct(
		public int $timeoutSeconds,
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
		);
	}

}
