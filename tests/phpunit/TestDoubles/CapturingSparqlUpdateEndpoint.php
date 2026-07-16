<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlUpdateEndpoint;

/**
 * Records the SPARQL updates posted to it, so tests can assert on the exact update string the store
 * builds without any HTTP.
 */
class CapturingSparqlUpdateEndpoint implements SparqlUpdateEndpoint {

	/**
	 * @var string[]
	 */
	public array $updates = [];

	public function postUpdate( string $update ): void {
		$this->updates[] = $update;
	}

	public function lastUpdate(): string {
		return $this->updates[array_key_last( $this->updates )];
	}

}
