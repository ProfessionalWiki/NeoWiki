<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

/**
 * Marks a value that needs to be wrapped in a Cypher constructor function such
 * as `datetime()`, `date()`, `point()`, or `duration()` rather than written as
 * a plain scalar. Returned by builders registered with
 * {@see Neo4jValueBuilderRegistry} when a property's storage requires a typed
 * Neo4j property (so it can be queried with Cypher's temporal/spatial
 * functions).
 */
readonly class Neo4jTypedValue {

	/**
	 * @param string $cypherFunction Cypher constructor function name, e.g. 'datetime'.
	 * @param mixed $value Raw scalar or list of scalars passed as a Cypher
	 *                     parameter and wrapped in the constructor.
	 */
	public function __construct(
		public string $cypherFunction,
		public mixed $value,
	) {
	}

}
