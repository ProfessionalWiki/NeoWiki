<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence;

use MediaWiki\Http\HttpRequestFactory;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlUpdateFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlUpdateEndpoint;
use StatusValue;
use Wikimedia\Message\MessageSpecifier;

/**
 * Posts SPARQL 1.1 Update requests over HTTP: the update text is the request body with a
 * `application/sparql-update` content type (SPARQL 1.1 Protocol § 2.2). A configured access token is
 * sent as an HTTP Bearer token, matching QLever's access-token scheme.
 *
 * No connection is attempted at construction, so building the plugin stays I/O-free. Any non-2xx
 * response or transport failure (HTTP status 0) throws {@see SparqlUpdateFailedException}.
 */
readonly class HttpSparqlUpdateEndpoint implements SparqlUpdateEndpoint {

	private const string CONTENT_TYPE = 'application/sparql-update';

	public function __construct(
		private HttpRequestFactory $httpRequestFactory,
		private string $updateUrl,
		private ?string $accessToken,
		private int $timeoutSeconds,
	) {
	}

	public function postUpdate( string $update ): void {
		$request = $this->httpRequestFactory->create(
			$this->updateUrl,
			[ 'method' => 'POST', 'postData' => $update, 'timeout' => $this->timeoutSeconds ],
			__METHOD__
		);

		$request->setHeader( 'Content-Type', self::CONTENT_TYPE );

		if ( $this->accessToken !== null ) {
			$request->setHeader( 'Authorization', 'Bearer ' . $this->accessToken );
		}

		$outcome = $request->execute();

		$status = $request->getStatus();
		if ( $status < 200 || $status >= 300 ) {
			throw new SparqlUpdateFailedException(
				$this->updateUrl,
				$status,
				self::failureReasons( $outcome ),
				$request->getContent()
			);
		}
	}

	/**
	 * Tells a store that took too long to answer (http-timed-out) from one that could not be reached
	 * (http-curl-error).
	 *
	 * @return string[]
	 */
	private static function failureReasons( StatusValue $outcome ): array {
		return array_map(
			static fn ( MessageSpecifier $message ): string => $message->getKey(),
			$outcome->getMessages( 'error' )
		);
	}

}
