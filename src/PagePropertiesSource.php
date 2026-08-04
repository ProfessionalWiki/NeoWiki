<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;

/**
 * The Page Properties to project for a revision.
 *
 * Building them parses the page and runs the registered Page Property Providers, either of which can
 * throw for a page MediaWiki can no longer fully handle. Whether that failure is swallowed or
 * propagates is the caller's choice, made by which implementation it is given: the hook-facing write
 * path isolates it so a projection failure never aborts the user's edit, and the maintenance rebuild
 * path lets it through so the script can report which pages failed to reconcile, and why. This mirrors
 * the same split on the graph plugins (see FailureIsolatingGraphDatabasePlugin and NeoWikiExtension).
 *
 * Null means the properties could not be built and the page must not be projected. Only the isolating
 * implementation returns it; PagePropertiesBuilder always either answers or throws.
 */
interface PagePropertiesSource {

	public function getPagePropertiesFor( RevisionRecord $revision, ?UserIdentity $user ): ?PageProperties;

}
