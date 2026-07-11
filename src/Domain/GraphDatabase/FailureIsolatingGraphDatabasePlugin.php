<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

use Exception;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DBError;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * Wraps a single graph database plugin so a projection failure never aborts the wiki
 * edit/delete/undelete that triggered it.
 *
 * The graph projection is derived, rebuildable state (see the RebuildGraphDatabases maintenance
 * script), so a failing backend must not fail the triggering user operation. A throwing plugin is
 * caught at the \Exception boundary — which covers the connection and transaction failures a down
 * Neo4j (or SPARQL endpoint) raises — logged as an error on the NeoWiki channel, and swallowed, so
 * the triggering operation still commits.
 *
 * Two kinds of throwable are deliberately let through instead of swallowed:
 *
 * - \Error is not caught at all. A programming bug (type error, bad method call) must surface as a
 *   bug, not masquerade as a backend outage.
 * - TimeoutException and DBError are re-thrown. A request timeout must keep aborting the request
 *   rather than being defeated here, and a wiki-database error belongs to the triggering operation:
 *   swallowing it would only mask the root cause of the commit failure that is coming anyway.
 *
 * Isolation is per plugin, so composing several of these (one per backend) lets one backend fail
 * without starving the others. This decorator is used only on the hook-facing write path; the
 * RebuildGraphDatabases maintenance path deliberately runs the propagating
 * CompositeGraphDatabasePlugin so it can report which pages failed to reconcile. See NeoWikiExtension.
 */
class FailureIsolatingGraphDatabasePlugin implements GraphDatabasePlugin {

	public function __construct(
		private readonly GraphDatabasePlugin $plugin,
		private readonly LoggerInterface $logger,
	) {
	}

	public function savePage( Page $page ): void {
		try {
			$this->plugin->savePage( $page );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->logProjectionFailure( 'save', $page->getId(), $e );
		}
	}

	public function deletePage( PageId $pageId ): void {
		try {
			$this->plugin->deletePage( $pageId );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->logProjectionFailure( 'delete', $pageId, $e );
		}
	}

	private function logProjectionFailure( string $operation, PageId $pageId, Exception $e ): void {
		$this->logger->error(
			'NeoWiki failed to ' . $operation . ' page ' . $pageId->id . ' in graph backend '
			. $this->plugin::class . '. The triggering operation was not aborted, but this backend is '
			. 'now out of sync for that page. Run the RebuildGraphDatabases maintenance script to '
			. 'reconcile it. Underlying error: ' . $e->getMessage(),
			[ 'exception' => $e ]
		);
	}

}
