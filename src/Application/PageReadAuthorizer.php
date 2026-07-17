<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * The binding per-page authorization for reading page-backed content: Subjects, Schemas, Layouts
 * and Mappings alike. Unlike SubjectPermissionHints this is not advisory, and unlike
 * SubjectWriteAuthorizer it is side-effect-free apart from rate-limit accounting ('read' is not a
 * limitable action in MediaWiki).
 *
 * Callers present a denial exactly like absent data (the endpoint's existing not-found shape),
 * never as an error, so that restricted pages cannot be enumerated through these endpoints.
 *
 * Both entry points exist because callers hold different keys: Application queries resolve a
 * Subject to a PageId through the graph, while the name-keyed Persistence lookups already hold the
 * Title they resolved for their own content fetch.
 */
interface PageReadAuthorizer {

	public function authorizeReadByPageId( PageId $pageId ): bool;

	public function authorizeReadByPageTitle( Title $title ): bool;

}
