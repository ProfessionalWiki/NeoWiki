<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;

class Neo4jValueBuilderRegistry {

	/**
	 * @var array<string, callable(NeoValue): mixed>
	 */
	private array $builders = [];

	/**
	 * @param callable(NeoValue): mixed $builder
	 */
	public function registerBuilder( string $propertyTypeName, callable $builder ): void {
		$this->builders[$propertyTypeName] = $builder;
	}

	public function buildNeo4jValue( string $propertyTypeName, NeoValue $value ): mixed {
		if ( !array_key_exists( $propertyTypeName, $this->builders ) ) {
			return null;
		}

		return $this->builders[$propertyTypeName]( $value );
	}

	public function hasBuilder( string $propertyTypeName ): bool {
		return array_key_exists( $propertyTypeName, $this->builders );
	}

	public static function withCoreBuilders(): self {
		$registry = new self();

		$toScalars = static fn( NeoValue $value ): mixed => $value->toScalars();

		$registry->registerBuilder( 'text', $toScalars );
		$registry->registerBuilder( 'url', $toScalars );
		$registry->registerBuilder( 'number', $toScalars );
		$registry->registerBuilder( 'select', $toScalars );
		$registry->registerBuilder( 'boolean', $toScalars );
		$registry->registerBuilder(
			'dateTime',
			static fn( NeoValue $value ): Neo4jTypedValue => new Neo4jTypedValue( 'datetime', $value->toScalars() )
		);
		$registry->registerBuilder( 'date', $toScalars );

		return $registry;
	}

}
