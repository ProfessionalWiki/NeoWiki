<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SchemaReferenceResolver;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;

/**
 * Serves both the name-keyed SchemaLookup and the reference-keyed SchemaReferenceResolver: test
 * references are local, so resolving one is just a name lookup. Letting one double satisfy both
 * keeps consumers that hold a lookup and a resolver on the same seam sharing a single instance.
 */
class InMemorySchemaLookup implements SchemaLookup, SchemaReferenceResolver {

	/**
	 * @var array<string, ?Schema>
	 */
	private array $schemas = [];

	public function __construct( Schema ...$schemas ) {
		array_walk( $schemas, $this->updateSchema( ... ) );
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		return $this->schemas[$schemaName->getText()] ?? null;
	}

	public function resolve( SchemaReference $reference ): ?Schema {
		return $this->getSchema( $reference->getName() );
	}

	public function updateSchema( Schema $schema ): void {
		$this->schemas[$schema->getName()->getText()] = $schema;
	}

}
