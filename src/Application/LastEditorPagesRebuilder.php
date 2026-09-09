<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Persistence\LastEditorPageIdsLookup;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Reprojects every page a user is the last editor of, which is what has to happen when whether the
 * wiki shows their name changes: hiding a name with RevisionDelete changes one revision, but hiding
 * a user with a block changes every revision they made at once (#1246).
 *
 * Every read is from the primary database — the page ids, the titles they resolve to, and the
 * revision each page is reprojected from. This runs out of band, and a replica that has yet to
 * catch up would both miss pages the user had just come to head and write the very name this is
 * undoing back onto the ones it did find.
 *
 * A page deleted since the work was queued is skipped, which is the right answer — it has no node
 * left to correct.
 */
class LastEditorPagesRebuilder {

	public function __construct(
		private readonly LastEditorPageIdsLookup $pageIdsLookup,
		private readonly PageRebuilder $pageRebuilder,
		private readonly TitleFactory $titleFactory,
	) {
	}

	public function rebuildPagesLastEditedBy( UserIdentity $user ): void {
		foreach ( $this->pageIdsLookup->getPageIdsLastEditedBy( $user ) as $pageId ) {
			$title = $this->titleFactory->newFromID( $pageId, IDBAccessObject::READ_LATEST );

			if ( $title !== null ) {
				$this->pageRebuilder->rebuildFromPrimary( $title );
			}
		}
	}

}
