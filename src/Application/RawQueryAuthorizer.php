<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

/**
 * Authorizes running a caller-supplied Cypher or SPARQL query. A raw query has whole-store read
 * semantics (ADR 27): its rows are not attributable to pages, so nothing is trimmed per row and the
 * whole surface is gated by one wiki-level decision instead. The query services check this
 * themselves, so every surface that runs a raw query (REST, wikitext, Lua) is gated the same way.
 */
interface RawQueryAuthorizer {

	public function authorizeRawQuery(): bool;

}
