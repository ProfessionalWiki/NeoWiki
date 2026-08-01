<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

/**
 * What a graph database backend says when it fails, made safe to keep.
 *
 * A backend signals failure by throwing (see {@see GraphDatabasePlugin}), and its client reports an
 * unreachable server by quoting the connection URI it tried, credentials included. Those messages end
 * up on an operator's terminal, in deployment logs, and in the rebuild run records, so they outlive the
 * failure they describe.
 */
final class BackendFailureMessage {

	/**
	 * Strips the userinfo out of any URI in the message.
	 *
	 * The run is bounded only by whitespace and matched greedily up to its last `@`, because a password
	 * may itself contain `/`, `@` or a quote: stopping at the first of those leaves the rest of it in
	 * the message. The cost is that a credential-free URI whose path holds an `@` loses that path, which
	 * is the right way round for a redaction.
	 */
	public static function withoutCredentials( string $message ): string {
		return (string)preg_replace( '#(?<=://)\S*@#', '', $message );
	}

}
