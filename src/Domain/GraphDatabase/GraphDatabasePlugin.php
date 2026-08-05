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
 * - On a graph rebuild, the plugin is used unwrapped, so failures reach the rebuild. It decides what
 *   each one costs: a page that will not project is logged and counted, while a store that cannot be
 *   reached — including one whose initialize() throws — ends that store's run.
 * - On update.php, the wiring likewise propagates, but the caller reports an initialize() failure and
 *   lets the update continue, since aborting there would only skip the schema updates of the
 *   extensions queued after NeoWiki.
 *
 * The hook path never initializes.
 */
interface GraphDatabasePlugin {

	/**
	 * Prepares the backing store for projections by creating the store-level structures the backend
	 * needs — such as uniqueness constraints — where it supports them. Idempotent, so its callers can
	 * run it every time. update.php calls this so an ordinary install or upgrade establishes those
	 * structures, and a rebuild calls it on the store it is scoped to before re-projecting into it, so a
	 * rebuilt graph carries them; the incremental per-edit path does not.
	 */
	public function initialize(): void;

	public function savePage( Page $page ): void;

	public function deletePage( PageId $pageId ): void;

}
