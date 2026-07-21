<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;

/**
 * NeoWiki-specific Authority fixtures on top of core's MockAuthorityTrait. Use this trait
 * INSTEAD of MockAuthorityTrait (it re-exposes all of its methods); using both in one class
 * is a trait-method collision.
 */
trait NeoWikiMockAuthorityTrait {

	use MockAuthorityTrait;

	/**
	 * Holds the wiki-global 'read' right, but cannot read any specific page
	 * (as under a restricted namespace, $wgWhitelistRead, or an ACL extension).
	 */
	private function authorityWithGlobalReadButNoPageRead(): Authority {
		$canReadGloballyButNotPerPage = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$permission === 'read' && $page === null;

		return $this->mockRegisteredAuthority( $canReadGloballyButNotPerPage );
	}

	/**
	 * Can read every page and holds the wiki-global 'edit' right, but cannot edit any specific page
	 * (as under page protection or an ACL extension that grants read but denies edit). Such a page
	 * is readable, so its existence is already public: a write to it is answered with 403, not the
	 * not-found shape reserved for pages the caller cannot read.
	 */
	private function authorityWithGlobalEditButNoPageEdit(): Authority {
		$canReadAnyPageAndEditGloballyButNotPerPage = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$permission === 'read' || ( $permission === 'edit' && $page === null );

		return $this->mockRegisteredAuthority( $canReadAnyPageAndEditGloballyButNotPerPage );
	}

}
