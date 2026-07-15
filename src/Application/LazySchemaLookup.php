<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Closure;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

/**
 * Builds the wrapped SchemaLookup on demand rather than at construction. Constructing the real
 * schema lookup pulls in the property-type registry, which fires the NeoWikiRegistration hook;
 * seams assembled before that hook may run (see NeoWikiExtension::newLocalSource()) must defer it.
 */
readonly class LazySchemaLookup implements SchemaLookup {

	/**
	 * @param Closure(): SchemaLookup $schemaLookupFactory
	 */
	public function __construct(
		private Closure $schemaLookupFactory
	) {
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		return ( $this->schemaLookupFactory )()->getSchema( $schemaName );
	}

}
