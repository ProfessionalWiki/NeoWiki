<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use MediaWiki\User\UserIdentity;

interface LastEditorPageIdsLookup {

	/**
	 * The id of every page whose current revision the given user made, in ascending order — the pages
	 * the graph names them the `lastEditor` of, and so the pages that have to be reprojected when
	 * whether the wiki shows their name changes.
	 *
	 * @return int[]
	 */
	public function getPageIdsLastEditedBy( UserIdentity $user ): array;

}
