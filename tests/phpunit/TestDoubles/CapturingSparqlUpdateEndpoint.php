<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlUpdateEndpoint;
use Throwable;

/**
 * Records the SPARQL updates posted to it, so tests can assert on the exact update string the store
 * builds without any HTTP.
 */
class CapturingSparqlUpdateEndpoint implements SparqlUpdateEndpoint {

	/**
	 * @var string[]
	 */
	public array $updates = [];

	/**
	 * @param Throwable|null $failure What posting throws, standing in for a store that cannot take
	 *        updates. Posting records the update before throwing, so a test can still see what was sent.
	 */
	public function __construct(
		private readonly ?Throwable $failure = null
	) {
	}

	public function postUpdate( string $update ): void {
		$this->updates[] = $update;

		if ( $this->failure !== null ) {
			throw $this->failure;
		}
	}

	public function lastUpdate(): string {
		return $this->updates[array_key_last( $this->updates )];
	}

}
