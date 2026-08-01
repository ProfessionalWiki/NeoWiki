<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The graph database backends contributed by extensions, each under a name that identifies it.
 *
 * The name is what a scoped graph rebuild is addressed by, and what its run records are filed under, so
 * it must identify exactly one backend. A plugin repeating a name already taken is skipped with a
 * warning, the way a configured store repeating one is: keeping the first registration leaves the
 * backends registered before it projecting as they were.
 */
class GraphDatabasePluginRegistry {

	/**
	 * @var array<string, GraphDatabasePlugin> Keys are store names
	 */
	private array $plugins = [];

	public function __construct(
		private readonly LoggerInterface $logger = new NullLogger(),
	) {
	}

	public function addPlugin( string $name, GraphDatabasePlugin $plugin ): void {
		if ( isset( $this->plugins[$name] ) ) {
			$this->logger->warning(
				'Ignoring the graph database plugin registered as "{name}": that name is already taken. '
				. 'Namespace it to the extension registering it.',
				[ 'name' => $name ]
			);
			return;
		}

		$this->plugins[$name] = $plugin;
	}

	/**
	 * @return array<string, GraphDatabasePlugin> Keys are store names, in registration order
	 */
	public function getPlugins(): array {
		return $this->plugins;
	}

}
