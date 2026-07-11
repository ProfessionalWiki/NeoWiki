<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

use Exception;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use Psr\Log\LoggerInterface;

/**
 * Fans a page event out to every registered graph database plugin, isolating failures per plugin.
 *
 * The graph projection is derived, rebuildable state (see the RebuildGraphDatabases maintenance
 * script), so a projection write must never abort the wiki edit/delete that triggered it, and one
 * failing backend must not stop the others from being written. Each plugin call is therefore
 * isolated: a throwing plugin is logged as an error and skipped, and the remaining plugins still run.
 */
class CompositeGraphDatabasePlugin implements GraphDatabasePlugin {

	/**
	 * @var GraphDatabasePlugin[]
	 */
	private array $plugins;

	public function __construct(
		private readonly LoggerInterface $logger,
		GraphDatabasePlugin ...$plugins
	) {
		$this->plugins = $plugins;
	}

	public function savePage( Page $page ): void {
		foreach ( $this->plugins as $plugin ) {
			try {
				$plugin->savePage( $page );
			} catch ( Exception $e ) {
				$this->logProjectionFailure( $plugin, 'save', $page->getId(), $e );
			}
		}
	}

	public function deletePage( PageId $pageId ): void {
		foreach ( $this->plugins as $plugin ) {
			try {
				$plugin->deletePage( $pageId );
			} catch ( Exception $e ) {
				$this->logProjectionFailure( $plugin, 'delete', $pageId, $e );
			}
		}
	}

	private function logProjectionFailure( GraphDatabasePlugin $plugin, string $operation, PageId $pageId, Exception $e ): void {
		$backend = $plugin::class;

		$this->logger->error(
			"NeoWiki failed to {$operation} page {$pageId->id} in graph backend {$backend}. "
			. 'The wiki edit was not blocked, but this backend is now out of sync for that page. '
			. 'Run the RebuildGraphDatabases maintenance script to reconcile it. '
			. 'Underlying error: ' . $e->getMessage(),
			[ 'exception' => $e ]
		);
	}

}
