<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use InvalidArgumentException;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Contracts\AuthenticateInterface;

/**
 * A configured Neo4j URL, split into the address the driver quotes in its errors and the credentials it must
 * not. The credentials end at the last `@`, not where a URL parser would end them: a password may hold any
 * character, and one with an unescaped `/`, `?` or `#` parses as a different URL with the password in its path.
 */
readonly class Neo4jUrl {

	private function __construct(
		public string $address,
		public AuthenticateInterface $authentication,
	) {
	}

	public static function fromString( string $url ): self {
		if ( !preg_match( '#^(.*?://)(?:(.*)@)?(.*)$#s', $url, $parts ) ) {
			throw new InvalidArgumentException( 'A Neo4j URL starts with its scheme, such as bolt:// or neo4j://.' );
		}

		return new self( $parts[1] . $parts[3], self::authenticationFrom( $parts[2] ) );
	}

	private static function authenticationFrom( string $credentials ): AuthenticateInterface {
		$usernameAndPassword = explode( ':', $credentials, 2 );

		if ( count( $usernameAndPassword ) === 2 ) {
			return Authenticate::basic( $usernameAndPassword[0], $usernameAndPassword[1] );
		}

		return Authenticate::disabled();
	}

}
