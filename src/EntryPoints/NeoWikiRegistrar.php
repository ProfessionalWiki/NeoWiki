<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jValueBuilderRegistry;

readonly class NeoWikiRegistrar {

	public function __construct(
		private PropertyTypeRegistry $propertyTypeRegistry,
		private Neo4jValueBuilderRegistry $valueBuilderRegistry,
		private PagePropertyProviderRegistry $pagePropertyProviderRegistry,
	) {
	}

	public function addPropertyType( PropertyType $type ): void {
		$this->propertyTypeRegistry->registerType( $type );
	}

	/**
	 * @param callable(NeoValue): mixed $builder
	 */
	public function addNeo4jValueBuilder( string $propertyTypeName, callable $builder ): void {
		$this->valueBuilderRegistry->registerBuilder( $propertyTypeName, $builder );
	}

	public function addPagePropertyProvider( PagePropertyProvider $provider ): void {
		$this->pagePropertyProviderRegistry->addProvider( $provider );
	}

}
