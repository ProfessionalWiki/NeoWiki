<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSchema;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaSerializer;

class GetSchemaQuery {

	public function __construct(
		private GetSchemaPresenter $presenter,
		private SchemaLookup $schemaLookup,
		private SchemaSerializer $serializer
	) {
	}

	public function execute( string $schemaName ): void {

		$schemaId = new SchemaId( $schemaName );

		$schema = $this->schemaLookup->getSchema( $schemaId );

		if ( $schema === null ) {
			$this->presenter->presentSchemaNotFound();
			return;
		}

		$this->presenter->presentSchema( $this->serializer->serialize( $schema ) );
	}

}
