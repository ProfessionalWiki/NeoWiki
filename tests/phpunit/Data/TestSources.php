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
	 * A registry holding the local Source and one other, so a bare id and an id from
	 * {@see TestSubjectIds::OTHER_SOURCE_KEY} both resolve, while any further Source key does not.
	 */
	public static function newRegistry(): SourceRegistry {
		$registry = new SourceRegistry( TestSubjectIds::LOCAL_SOURCE_KEY );

		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, self::newSource() );
		$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, self::newSource() );

		return $registry;
	}

	private static function newSource(): LocalSource {
		return new LocalSource(
			fn (): InMemorySubjectLookup => new InMemorySubjectLookup(),
			new InMemorySchemaLookup(),
			'https://example.org/entity/'
		);
	}

	/**
	 * A resolver over a wiki whose only Source is the local one, serving $schemaLookup's Schemas.
	 */
	public static function newSchemaResolver( ?SchemaLookup $schemaLookup = null ): SchemaResolver {
		$registry = new SourceRegistry( TestSubjectIds::LOCAL_SOURCE_KEY );

		$registry->registerSource(
			TestSubjectIds::LOCAL_SOURCE_KEY,
			new LocalSource(
				fn (): InMemorySubjectLookup => new InMemorySubjectLookup(),
				$schemaLookup ?? new InMemorySchemaLookup(),
				'https://example.org/entity/'
			)
		);

		return new SchemaResolver( $registry, new NullLogger() );
	}

}
