<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\GraphDatabase;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePluginRegistry;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphStoreName;
use Psr\Log\Test\TestLogger;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePluginRegistry
 */
class GraphDatabasePluginRegistryTest extends TestCase {

	public function testPluginsAreKeyedByTheirName(): void {
		$registry = new GraphDatabasePluginRegistry();
		$first = new SpyGraphDatabasePlugin();
		$second = new SpyGraphDatabasePlugin();

		$registry->addPlugin( 'first', $first );
		$registry->addPlugin( 'second', $second );

		$this->assertSame( [ 'first' => $first, 'second' => $second ], $registry->getPlugins() );
	}

	public function testRegistrationOrderIsPreserved(): void {
		$registry = new GraphDatabasePluginRegistry();

		$registry->addPlugin( 'zulu', new SpyGraphDatabasePlugin() );
		$registry->addPlugin( 'alpha', new SpyGraphDatabasePlugin() );
		$registry->addPlugin( 'mike', new SpyGraphDatabasePlugin() );

		$this->assertSame( [ 'zulu', 'alpha', 'mike' ], array_keys( $registry->getPlugins() ) );
	}

	public function testAPluginRepeatingATakenNameIsIgnored(): void {
		$registry = new GraphDatabasePluginRegistry();
		$first = new SpyGraphDatabasePlugin();

		$registry->addPlugin( 'store', $first );
		$registry->addPlugin( 'store', new SpyGraphDatabasePlugin() );

		$this->assertSame( [ 'store' => $first ], $registry->getPlugins() );
	}

	public function testAPluginRepeatingATakenNameIsWarnedAbout(): void {
		$logger = new TestLogger();
		$registry = new GraphDatabasePluginRegistry( $logger );

		$registry->addPlugin( 'store', new SpyGraphDatabasePlugin() );
		$registry->addPlugin( 'store', new SpyGraphDatabasePlugin() );

		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public function testAPluginTakingAReservedNameIsIgnored(): void {
		$registry = new GraphDatabasePluginRegistry();
		$registry->reserveNames( 'neo4j' );

		$registry->addPlugin( 'neo4j', new SpyGraphDatabasePlugin() );

		$this->assertSame( [], $registry->getPlugins() );
	}

	public function testAPluginTakingAReservedNameIsWarnedAbout(): void {
		$logger = new TestLogger();
		$registry = new GraphDatabasePluginRegistry( $logger );
		$registry->reserveNames( 'neo4j' );

		$registry->addPlugin( 'neo4j', new SpyGraphDatabasePlugin() );

		$this->assertTrue( $logger->hasWarningThatContains( 'held by a bundled backend' ) );
	}

	public function testReservingANameLeavesTheOtherNamesFree(): void {
		$registry = new GraphDatabasePluginRegistry();
		$registry->reserveNames( 'neo4j', 'EDM' );
		$plugin = new SpyGraphDatabasePlugin();

		$registry->addPlugin( 'redherb', $plugin );

		$this->assertSame( [ 'redherb' => $plugin ], $registry->getPlugins() );
	}

	/**
	 * A name the run records cannot hold whole is cut to fit when a run is filed, and every lookup then
	 * passes the uncut name and matches nothing — so the store's rebuilds never find their own records.
	 */
	public function testAPluginWhoseNameTheRunRecordsCannotHoldIsIgnored(): void {
		$registry = new GraphDatabasePluginRegistry();

		$registry->addPlugin( str_repeat( 'a', GraphStoreName::MAX_LENGTH + 1 ), new SpyGraphDatabasePlugin() );

		$this->assertSame( [], $registry->getPlugins() );
	}

	public function testAPluginNamedExactlyAsLongAsTheRunRecordsAllowIsRegistered(): void {
		$registry = new GraphDatabasePluginRegistry();
		$name = str_repeat( 'a', GraphStoreName::MAX_LENGTH );

		$registry->addPlugin( $name, new SpyGraphDatabasePlugin() );

		$this->assertSame( [ $name ], array_keys( $registry->getPlugins() ) );
	}

	/**
	 * The bundled backends' names are reserved whatever the casing, as they are for a configured store:
	 * a plugin registered as "Neo4j" would otherwise stand beside the bundled one and read as it
	 * wherever a store name is written or reported.
	 */
	public function testAPluginTakingAReservedNameInAnotherCasingIsIgnored(): void {
		$registry = new GraphDatabasePluginRegistry();
		$registry->reserveNames( 'neo4j' );

		$registry->addPlugin( 'Neo4j', new SpyGraphDatabasePlugin() );

		$this->assertSame( [], $registry->getPlugins() );
	}

	public function testEmptyRegistryHasNoPlugins(): void {
		$this->assertSame( [], ( new GraphDatabasePluginRegistry() )->getPlugins() );
	}

}
