<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Side-effect-free permission queries, for showing or hiding UI affordances and for cheap early
 * access denial. They are advisory: a positive answer is not authorization to write. Authorize an
 * actual write with SubjectWriteAuthorizer.
 */
interface SubjectPermissionHints {

	public function canCreateMainSubject( ?PageId $pageId ): bool;

	public function canCreateChildSubject( ?PageId $pageId ): bool;

	public function canEditSubject( ?PageId $pageId ): bool;

}
