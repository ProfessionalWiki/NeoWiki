<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application;

/**
 * Sends a SPARQL 1.1 Update request to a store's endpoint. Implementations throw on any failure
 * (a non-2xx response or a transport error); the caller's failure isolation decides the consequences.
 */
interface SparqlUpdateEndpoint {

	public function postUpdate( string $update ): void;

}
