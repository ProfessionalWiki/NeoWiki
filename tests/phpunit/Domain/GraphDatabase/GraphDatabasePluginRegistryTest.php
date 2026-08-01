<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\GraphDatabase;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePluginRegistry;
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

	public function testEmptyRegistryHasNoPlugins(): void {
		$this->assertSame( [], ( new GraphDatabasePluginRegistry() )->getPlugins() );
	}

}
