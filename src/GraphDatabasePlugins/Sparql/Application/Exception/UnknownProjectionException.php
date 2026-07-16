<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception;

use RuntimeException;

/**
 * Thrown when a SPARQL store is configured with a projection name that is neither "native" nor any
 * declared Mapping target (e.g. its Mapping page was deleted). On the hook path the per-plugin failure
 * isolation logs it per edit; on the rebuild path it is reported per page. The message names the store,
 * the bad projection, and the known projections.
 */
class UnknownProjectionException extends RuntimeException {

	/**
	 * @param string[] $knownProjectionNames
	 */
	public function __construct( string $endpointUrl, string $projectionName, array $knownProjectionNames ) {
		parent::__construct(
			'The SPARQL store <' . $endpointUrl . '> is configured with unknown projection "'
			. $projectionName . '". Known projections: ' . implode( ', ', $knownProjectionNames ) . '.'
		);
	}

}
