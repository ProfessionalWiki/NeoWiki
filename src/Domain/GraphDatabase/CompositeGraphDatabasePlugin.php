<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Fans a page event out to every registered graph database plugin.
 *
 * This composite propagates failures: if a plugin throws, the throw escapes and later plugins do
 * not run. That is what the RebuildGraphDatabases maintenance script needs, so it can report which
 * pages failed to reconcile. The hook-facing production wiring wraps each plugin in a
 * FailureIsolatingGraphDatabasePlugin so a projection failure never aborts the triggering user
 * operation; see NeoWikiExtension.
 */
class CompositeGraphDatabasePlugin implements GraphDatabasePlugin {

	/**
	 * @var GraphDatabasePlugin[]
	 */
	private array $plugins;

	public function __construct( GraphDatabasePlugin ...$plugins ) {
		$this->plugins = $plugins;
	}

	public function initialize(): void {
		foreach ( $this->plugins as $plugin ) {
			$plugin->initialize();
		}
	}

	public function savePage( Page $page ): void {
		foreach ( $this->plugins as $plugin ) {
			$plugin->savePage( $page );
		}
	}

	public function deletePage( PageId $pageId ): void {
		foreach ( $this->plugins as $plugin ) {
			$plugin->deletePage( $pageId );
		}
	}

}
