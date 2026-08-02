<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

/**
 * What a graph database backend says when it fails, made safe to keep.
 *
 * A backend signals failure by throwing (see {@see GraphDatabasePlugin}), and its client reports what it
 * could not reach by quoting the request it made — a connection URI with its userinfo, an endpoint with
 * its access token in the query string, an Authorization header with its bearer token. Those messages end
 * up on an operator's terminal, in deployment logs, and in the rebuild run records, so they outlive the
 * failure they describe.
 *
 * What names each credential is kept and only the credential itself removed, so the message still says
 * which server was being reached and how it was being authenticated to.
 */
final class BackendFailureMessage {

	/**
	 * The parameters a store's credentials are passed under, however the backend spells them.
	 */
	private const string SECRET_PARAMETER = 'access[-_]?token|token|api[-_]?key|password';

	/**
	 * How far a credential runs. Deliberately narrow — the characters a token is made of — so that
	 * redacting one stops at the punctuation around it rather than eating the rest of the message: the
	 * status code after an endpoint is often the only part of it worth reading.
	 */
	private const string SECRET_VALUE = '[\w.~%+\/=-]+';

	public static function withoutCredentials( string $message ): string {
		$message = self::withoutUriUserinfo( $message );
		$message = self::withoutQueryStringSecrets( $message );

		return self::withoutBearerTokens( $message );
	}

	/**
	 * The run is bounded only by whitespace and matched greedily up to its last `@`, because a password
	 * may itself contain `/`, `@` or a quote: stopping at the first of those leaves the rest of it in
	 * the message. The cost is that a credential-free URI whose path holds an `@` loses that path, which
	 * is the right way round for a redaction.
	 */
	private static function withoutUriUserinfo( string $message ): string {
		return (string)preg_replace( '#(?<=://)\S*@#', '', $message );
	}

	private static function withoutQueryStringSecrets( string $message ): string {
		return (string)preg_replace(
			'/\b(' . self::SECRET_PARAMETER . ')=' . self::SECRET_VALUE . '/i',
			'$1=',
			$message
		);
	}

	/**
	 * Nothing distinguishes a token from any other word, so whatever follows "Bearer" is treated as one.
	 * Losing a word of prose is the right way round for a redaction.
	 */
	private static function withoutBearerTokens( string $message ): string {
		return (string)preg_replace( '/\bBearer\s+' . self::SECRET_VALUE . '/i', 'Bearer', $message );
	}

}
