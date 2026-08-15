<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeProvider;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeProviderRegistry;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePluginRegistry;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfTerm;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;

readonly class NeoWikiRegistrar {

	public function __construct(
		private PropertyTypeRegistry $propertyTypeRegistry,
		private Neo4jValueBuilderRegistry $valueBuilderRegistry,
		private PagePropertyProviderRegistry $pagePropertyProviderRegistry,
		private GraphDatabasePluginRegistry $graphDatabasePluginRegistry,
		private RdfValueMapperRegistry $rdfValueMapperRegistry,
		private SubjectEditNoticeProviderRegistry $subjectEditNoticeProviderRegistry,
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

	/**
	 * Registers how a Property Type's value projects to RDF terms for the native RDF export: a list of
	 * literals, or IRIs for values that denote a resource.
	 *
	 * @param callable(NeoValue): RdfTerm[] $mapper
	 */
	public function addRdfValueMapper( string $propertyTypeName, callable $mapper ): void {
		$this->rdfValueMapperRegistry->registerMapper( $propertyTypeName, $mapper );
	}

	/**
	 * Registers a source of messages shown to users before they edit a Subject. Providers are
	 * consulted in registration order, after the notices wiki admins write as interface messages.
	 */
	public function addSubjectEditNoticeProvider( SubjectEditNoticeProvider $provider ): void {
		$this->subjectEditNoticeProviderRegistry->addProvider( $provider );
	}

	public function addPagePropertyProvider( PagePropertyProvider $provider ): void {
		$this->pagePropertyProviderRegistry->addProvider( $provider );
	}

	/**
	 * Registers a graph database backend to project page changes into.
	 *
	 * The name identifies the backend across runs and installs: a graph rebuild is scoped to one store
	 * by it, and its run records are filed under it. Pick a stable one, and namespace it to your
	 * extension so it cannot collide with another's — a name already taken is ignored.
	 */
	public function addGraphDatabasePlugin( string $name, GraphDatabasePlugin $plugin ): void {
		$this->graphDatabasePluginRegistry->addPlugin( $name, $plugin );
	}

}
