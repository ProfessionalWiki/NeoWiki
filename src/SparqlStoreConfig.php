<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

/**
 * Configuration for a single SPARQL 1.1 graph store the SPARQL plugin (#586) projects into. One is
 * parsed from each `NeoWikiSparqlStores` entry (see {@see NeoWikiConfigFactory}).
 *
 * The projection names the RDF vocabulary the store holds — "native" or any Mapping target. It is
 * resolved lazily on each save (not here), so a store always tracks the current Mapping definitions.
 */
readonly class SparqlStoreConfig {

	public function __construct(
		public string $updateUrl,
		public ?string $accessToken,
		public string $projection,
	) {
	}

}
