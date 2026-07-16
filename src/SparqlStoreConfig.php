<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

/**
 * Configuration for a single SPARQL 1.1 graph store the SPARQL plugin (#586) projects into and queries.
 * One is parsed from each `NeoWikiSparqlStores` entry (see {@see NeoWikiConfigFactory}).
 *
 * `updateUrl` is the SPARQL 1.1 Update endpoint the write path posts to; `queryUrl` is the SPARQL 1.1
 * Query endpoint the read surfaces post to, defaulting to `updateUrl` (for QLever the two are the same
 * value). The `accessToken`, when set, is sent as an HTTP Bearer token on both.
 *
 * The projection names the RDF vocabulary the store holds — "native" or any Mapping target. It is
 * resolved lazily on each save (not here), so a store always tracks the current Mapping definitions.
 */
readonly class SparqlStoreConfig {

	public function __construct(
		public string $updateUrl,
		public string $queryUrl,
		public ?string $accessToken,
		public string $projection,
	) {
	}

}
