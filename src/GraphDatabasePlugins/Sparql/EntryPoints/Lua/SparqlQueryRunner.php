<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\Lua;

use MediaWiki\Context\RequestContext;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService;

/**
 * Runs a SPARQL query for nw.sparqlQuery() and returns the W3C results document as a Lua table,
 * preserving the standard `head` / `results.bindings` structure. The read-side sibling of
 * {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\Lua\CypherQueryRunner}.
 */
class SparqlQueryRunner {

	public function __construct(
		private readonly SparqlQueryService $queryService,
	) {
	}

	/**
	 * @return array<int|string, mixed> The results document with every JSON array re-indexed for Lua.
	 */
	public function run( string $sparql ): array {
		$result = $this->queryService->execute(
			new SparqlQueryRequest(
				sparql: $sparql,
				limits: SparqlQueryLimits::forUser( RequestContext::getMain()->getUser() ),
			)
		);

		return self::toLuaTable( $result->document );
	}

	/**
	 * Lua tables are 1-indexed, but json_decode produces 0-indexed lists (head.vars, results.bindings).
	 * Shift every integer key up by one — recursively, since the lists are nested — while leaving the
	 * string-keyed objects (bindings, RDF terms) untouched.
	 *
	 * @param array<int|string, mixed> $data
	 * @return array<int|string, mixed>
	 */
	private static function toLuaTable( array $data ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			$luaKey = is_int( $key ) ? $key + 1 : $key;
			$result[$luaKey] = is_array( $value ) ? self::toLuaTable( $value ) : $value;
		}

		return $result;
	}

}
