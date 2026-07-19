<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePluginRegistry;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPagePropertyProvider;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar
 */
class NeoWikiRegistrarTest extends TestCase {

	public function testAddPropertyTypeRegistersInRegistry(): void {
		$propertyTypeRegistry = new PropertyTypeRegistry();
		$registrar = $this->newRegistrar( propertyTypeRegistry: $propertyTypeRegistry );

		$registrar->addPropertyType( new TextType() );

		$this->assertNotNull( $propertyTypeRegistry->getType( 'text' ) );
	}

	public function testAddNeo4jValueBuilderRegistersInRegistry(): void {
		$valueBuilderRegistry = new Neo4jValueBuilderRegistry();
		$registrar = $this->newRegistrar( valueBuilderRegistry: $valueBuilderRegistry );

		$registrar->addNeo4jValueBuilder( 'custom', static fn() => 'value' );

		$this->assertTrue( $valueBuilderRegistry->hasBuilder( 'custom' ) );
	}

	public function testAddPagePropertyProviderRegistersInRegistry(): void {
		$providerRegistry = new PagePropertyProviderRegistry();
		$registrar = $this->newRegistrar( pagePropertyProviderRegistry: $providerRegistry );

		$provider = new StubPagePropertyProvider( [ 'foo' => 'bar' ] );
		$registrar->addPagePropertyProvider( $provider );

		$this->assertSame( [ $provider ], $providerRegistry->getProviders() );
	}

	public function testAddGraphDatabasePluginRegistersInRegistry(): void {
		$pluginRegistry = new GraphDatabasePluginRegistry();
		$registrar = $this->newRegistrar( graphDatabasePluginRegistry: $pluginRegistry );

		$plugin = new SpyGraphDatabasePlugin();
		$registrar->addGraphDatabasePlugin( $plugin );

		$this->assertSame( [ $plugin ], $pluginRegistry->getPlugins() );
	}

	public function testAddRdfValueMapperRegistersInRegistry(): void {
		$rdfValueMapperRegistry = new RdfValueMapperRegistry();
		$registrar = $this->newRegistrar( rdfValueMapperRegistry: $rdfValueMapperRegistry );

		$registrar->addRdfValueMapper( 'custom', static fn() => [] );

		$this->assertTrue( $rdfValueMapperRegistry->hasMapper( 'custom' ) );
	}

	private function newRegistrar(
		?PropertyTypeRegistry $propertyTypeRegistry = null,
		?Neo4jValueBuilderRegistry $valueBuilderRegistry = null,
		?PagePropertyProviderRegistry $pagePropertyProviderRegistry = null,
		?GraphDatabasePluginRegistry $graphDatabasePluginRegistry = null,
		?RdfValueMapperRegistry $rdfValueMapperRegistry = null,
	): NeoWikiRegistrar {
		return new NeoWikiRegistrar(
			propertyTypeRegistry: $propertyTypeRegistry ?? new PropertyTypeRegistry(),
			valueBuilderRegistry: $valueBuilderRegistry ?? new Neo4jValueBuilderRegistry(),
			pagePropertyProviderRegistry: $pagePropertyProviderRegistry ?? new PagePropertyProviderRegistry(),
			graphDatabasePluginRegistry: $graphDatabasePluginRegistry ?? new GraphDatabasePluginRegistry(),
			rdfValueMapperRegistry: $rdfValueMapperRegistry ?? new RdfValueMapperRegistry(),
		);
	}

}
