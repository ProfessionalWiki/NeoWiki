<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;

/**
 * The access class of a user, for keying parser-cached output that depends on what the parsing user
 * may read: the user's effective groups plus the wiki-level `read` and `neowiki-query` decisions, as
 * a readable string such as `*,autoconfirmed,user;read;query`.
 *
 * Group membership is a proxy for per-page read permission, exact wherever that permission is a
 * function of groups (private wikis, namespace lockdowns) and wrong for hooks that grant per user.
 * The wiki-level rights are in the class because hooks can grant them outside groups.
 *
 * Every reader has a class, the anonymous one included, so that an entry cached before NeoWiki began
 * keying by class is never reused for a page that reads Subjects.
 */
class UserAccessClass {

	public function __construct(
		private readonly UserGroupManager $userGroupManager,
		private readonly PermissionManager $permissionManager,
	) {
	}

	public function of( UserIdentity $user ): string {
		$groups = $this->userGroupManager->getUserEffectiveGroups( $user );
		sort( $groups );

		// Group names come from hooks and the database, so they can hold the separators, and the
		// cache key turns spaces into underscores. Without encoding, a user in a group named
		// "sysop,user" would describe exactly like a sysop and read that class's cached output.
		return implode( ',', array_map( 'rawurlencode', $groups ) )
			. ( $this->permissionManager->userHasRight( $user, 'read' ) ? ';read' : '' )
			. ( $this->permissionManager->userHasRight( $user, AuthorityBasedRawQueryAuthorizer::RIGHT ) ? ';query' : '' );
	}

}
