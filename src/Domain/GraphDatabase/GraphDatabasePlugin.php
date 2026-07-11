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
 *   reported truthfully per page instead of being silently swallowed.
 */
interface GraphDatabasePlugin {

	public function savePage( Page $page ): void;

	public function deletePage( PageId $pageId ): void;

}
