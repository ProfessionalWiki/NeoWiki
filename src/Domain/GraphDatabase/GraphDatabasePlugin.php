<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Projects wiki page changes into a graph database backend.
 *
 * Implementations signal a projection failure by throwing. How that throw is handled depends on the
 * call path, not on the implementation:
 *
 * - On the hook-facing write path (edit/delete/undelete), the production wiring isolates and logs
 *   each plugin (via FailureIsolatingGraphDatabasePlugin), so a throw does not abort the triggering
 *   user operation and one failing backend does not starve the others.
 * - On maintenance rebuilds (RebuildGraphDatabases), the wiring propagates failures so they are
 *   reported truthfully per page instead of being silently swallowed. The rebuild is also the only
 *   path that calls initialize(), so an initialize() failure propagates like any other rebuild
 *   failure; the hook path never initializes.
 */
interface GraphDatabasePlugin {

	/**
	 * Prepares the backing store for projections by creating the store-level structures the backend
	 * needs — such as uniqueness constraints — where it supports them. Idempotent, so the rebuild can
	 * run it every time. The RebuildGraphDatabases maintenance path calls this before bulk re-projection
	 * so a rebuilt graph carries those structures; the incremental per-edit path does not.
	 */
	public function initialize(): void;

	public function savePage( Page $page ): void;

	public function deletePage( PageId $pageId ): void;

}
