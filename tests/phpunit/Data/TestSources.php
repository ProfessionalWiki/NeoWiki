<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use Psr\Log\NullLogger;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\Source\LocalSource;
use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

class TestSources {

	/**
	 * A resolver over a wiki whose only Source is the local one, serving $schemaLookup's Schemas.
	 */
	public static function newSchemaResolver( ?SchemaLookup $schemaLookup = null ): SchemaResolver {
		$registry = new SourceRegistry( TestSubjectIds::LOCAL_SOURCE_KEY );

		$registry->registerSource(
			TestSubjectIds::LOCAL_SOURCE_KEY,
			new LocalSource(
				new InMemorySubjectLookup(),
				$schemaLookup ?? new InMemorySchemaLookup(),
				'https://example.org/entity/'
			)
		);

		return new SchemaResolver( $registry, new NullLogger() );
	}

}
