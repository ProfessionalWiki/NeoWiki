<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * The binding per-page authorization for reading Subject data. Unlike SubjectPermissionHints this
 * is not advisory, and unlike SubjectWriteAuthorizer it is side-effect-free apart from rate-limit
 * accounting (no 'read' rate-limit bucket exists in a default MediaWiki install).
 *
 * Callers present a denial exactly like absent data (the endpoint's existing not-found shape),
 * never as an error, so that restricted pages cannot be enumerated through these endpoints.
 */
interface SubjectReadAuthorizer {

	public function authorizeRead( PageId $pageId ): bool;

}
