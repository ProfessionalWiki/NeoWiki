<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application;

use ProfessionalWiki\NeoWiki\Application\Rdf\RdfProjection;

/**
 * The narrow seam through which {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\SparqlProjectionStore}
 * resolves its configured projection, lazily on each save, without depending on the whole extension.
 *
 * Two operations, matching what core already exposes: obtain the projection for a name (null when the
 * name is neither "native" nor a declared Mapping target), and — only for the unknown-projection error
 * — list the known names.
 */
interface ProjectionResolver {

	public function newRdfProjection( string $projectionName ): ?RdfProjection;

	/**
	 * @return string[]
	 */
	public function getRdfProjectionNames(): array;

}
