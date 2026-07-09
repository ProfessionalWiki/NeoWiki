<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * The binding authorization for a write to a page's Subjects. Creating, replacing, deleting and
 * reordering Subjects are all edits of the page that holds them, so they share this authorization.
 *
 * Call this exactly once per write attempt, at the point of writing, and only when a write is
 * actually going to be attempted: unlike SubjectPermissionHints, this is not side-effect-free.
 * Policy effects, such as rate limit accounting, attach to this call and to no other.
 */
interface SubjectWriteAuthorizer {

	public function authorize( ?PageId $pageId ): bool;

}
