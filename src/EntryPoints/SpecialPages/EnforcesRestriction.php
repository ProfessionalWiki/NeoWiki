<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use PermissionsError;

/**
 * MediaWiki 1.46 made getRestriction() the one place a special page names the right it needs, and
 * pointed enforcement, listing and the denial page at it. Up to 1.45 those three instead read the
 * property the deprecated constructor parameter set, so each is restated here against
 * getRestriction(), leaving every supported version taking the right from one place.
 */
trait EnforcesRestriction {

	public function userCanExecute( User $user ): bool {
		return MediaWikiServices::getInstance()
			->getPermissionManager()
			->userHasRight( $user, $this->getRestriction() );
	}

	public function isRestricted(): bool {
		return !MediaWikiServices::getInstance()
			->getGroupPermissionsLookup()
			->groupHasPermission( '*', $this->getRestriction() );
	}

	protected function displayRestrictionError(): never {
		throw new PermissionsError( $this->getRestriction() );
	}

}
