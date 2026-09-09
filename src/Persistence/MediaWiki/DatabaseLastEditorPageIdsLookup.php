<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Persistence\LastEditorPageIdsLookup;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Answers from `page_latest`, so a page the user edited and someone else edited afterwards is not
 * theirs any more — which matches what the projection holds, since a page is projected from its
 * current revision alone.
 *
 * The whole set is returned rather than paged. What asks for it is a name being hidden or shown
 * again, and MediaWiki refuses to hide the name of a user with more edits than
 * `$wgHideUserContribLimit`, for this very reason. That bound is the default rather than a
 * guarantee: it can be set to false, and it is checked when hiding a name, not when showing one
 * again, so a user hidden under a laxer setting is walked in full.
 */
class DatabaseLastEditorPageIdsLookup implements LastEditorPageIdsLookup {

	public function __construct(
		private readonly IReadableDatabase $db,
		private readonly ActorNormalization $actorNormalization,
	) {
	}

	/**
	 * @return int[]
	 */
	public function getPageIdsLastEditedBy( UserIdentity $user ): array {
		$actorId = $this->actorNormalization->findActorId( $user, $this->db );

		if ( $actorId === null ) {
			return [];
		}

		return array_map( 'intval', $this->db->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			// Joined on both keys: `page` has no index on page_latest, so pairing them alone means a full
			// scan of it, while rev_page lets rev_actor_timestamp drive from the user's own revisions.
			->join( 'revision', null, [ 'page_latest = rev_id', 'rev_page = page_id' ] )
			->where( [ 'rev_actor' => $actorId ] )
			->orderBy( 'page_id' )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

}
