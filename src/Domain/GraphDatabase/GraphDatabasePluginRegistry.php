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
 * backends registered before it projecting as they were. The names the bundled backends answer to are
 * reserved here for the same reason, so a plugin taking one is refused rather than quietly dropped
 * where the two sets meet.
 */
class GraphDatabasePluginRegistry {

	/**
	 * @var array<string, GraphDatabasePlugin> Keys are store names
	 */
	private array $plugins = [];

	/**
	 * @var array<string, true> Keys are the store names the bundled backends hold
	 */
	private array $reservedNames = [];

	public function __construct(
		private readonly LoggerInterface $logger = new NullLogger(),
	) {
	}

	public function reserveNames( string ...$names ): void {
		foreach ( $names as $name ) {
			$this->reservedNames[$name] = true;
		}
	}

	public function addPlugin( string $name, GraphDatabasePlugin $plugin ): void {
		$rejection = $this->rejectionReason( $name );

		if ( $rejection !== null ) {
			$this->logger->warning(
				'Ignoring the graph database plugin registered as "{name}": ' . $rejection,
				[ 'name' => $name ]
			);
			return;
		}

		$this->plugins[$name] = $plugin;
	}

	/**
	 * Why this name cannot identify a store, as a message to log, or null when it can. The same rules a
	 * configured store's name is held to, because both end up as the key a rebuild is addressed by and
	 * as the value its run records are filed under.
	 */
	private function rejectionReason( string $name ): ?string {
		if ( GraphStoreName::isReserved( $name, $this->reservedNames ) ) {
			return 'that name is held by a bundled backend, in any casing. Namespace it to the '
				. 'extension registering it.';
		}

		if ( isset( $this->plugins[$name] ) ) {
			return 'that name is already taken. Namespace it to the extension registering it.';
		}

		if ( GraphStoreName::isTooLong( $name ) ) {
			return 'that name is longer than ' . GraphStoreName::MAX_LENGTH . ' bytes, which is all a '
				. 'rebuild can file its run records under. Register it under a shorter name.';
		}

		return null;
	}

	/**
	 * @return array<string, GraphDatabasePlugin> Keys are store names, in registration order
	 */
	public function getPlugins(): array {
		return $this->plugins;
	}

}
