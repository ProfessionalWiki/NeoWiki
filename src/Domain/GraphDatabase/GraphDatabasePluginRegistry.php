<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

class GraphDatabasePluginRegistry {

	/**
	 * @var GraphDatabasePlugin[]
	 */
	private array $plugins = [];

	public function addPlugin( GraphDatabasePlugin $plugin ): void {
		$this->plugins[] = $plugin;
	}

	/**
	 * @return GraphDatabasePlugin[]
	 */
	public function getPlugins(): array {
		return $this->plugins;
	}

}
