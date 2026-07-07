<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

interface SubjectAuthorizer {

	/**
	 * Side-effect-free capability checks, for UI hints (showing or hiding affordances) and cheap
	 * early access-denial. They do not enforce the edit rate limit; authorize an actual write
	 * with authorizeEdit().
	 */
	public function canCreateMainSubject( ?PageId $pageId ): bool;

	public function canCreateChildSubject( ?PageId $pageId ): bool;

	public function canEditSubject( ?PageId $pageId ): bool;

	/**
	 * Authorizes an actual write to the page's Subjects (create, replace, delete, reorder are all
	 * edits to the page). Unlike the can* checks, this enforces and counts the edit rate limit.
	 */
	public function authorizeEdit( ?PageId $pageId ): bool;

}
