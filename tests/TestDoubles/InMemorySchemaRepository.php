<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaRepository;

class InMemorySchemaRepository implements SchemaRepository {

	/**
	 * @var array<string, ?Schema>
	 */
	private array $schemas = [];

	public function __construct( Schema ...$schemas ) {
		array_walk( $schemas, $this->updateSchema( ... ) );
	}

	public function getSchema( SchemaId $schemaId ): ?Schema {
		return $this->schemas[$schemaId->getText()] ?? null;
	}

	public function updateSchema( Schema $schema ): void {
		$this->schemas[$schema->getId()->getText()] = $schema;
	}

}
