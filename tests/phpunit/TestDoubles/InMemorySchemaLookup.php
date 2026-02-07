<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

class InMemorySchemaLookup implements SchemaLookup {

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

	public function updateSchema( Schema $schema ): void {
		$this->schemas[$schema->getName()->getText()] = $schema;
	}

}
