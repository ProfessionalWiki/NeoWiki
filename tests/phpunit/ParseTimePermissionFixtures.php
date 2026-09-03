<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Fixtures for the parse-time permission tests, which live in two class hierarchies (the NeoWiki
 * integration base and Scribunto's Lua engine base) and so cannot share a base class.
 */
trait ParseTimePermissionFixtures {

	/**
	 * Denies anonymous users the read permission of the named pages, the way a per-page ACL
	 * extension would, while the request's user keeps every right.
	 */
	protected function denyAnonymousReadOf( string ...$pageNames ): void {
		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function ( Title $title, User $user, string $action, &$result ) use ( $pageNames ): bool {
				if ( $action === 'read' && $user->isAnon() && in_array( $title->getPrefixedText(), $pageNames, true ) ) {
					$result = [ 'badaccess-group0' ];
					return false;
				}

				return true;
			}
		);
	}

	protected function grantTheQueryRightToSysopsOnly(): void {
		$this->setGroupPermissions( '*', 'neowiki-query', false );
		$this->setGroupPermissions( 'sysop', 'neowiki-query', true );
	}

	/**
	 * Sysops hold `apihighlimits`, so a query sized for a sysop gets the expensive tier and both
	 * rows of a two-row query, while one sized for the default tier gets one.
	 */
	protected function capTheDefaultTierAtOneRow(): void {
		$this->overrideConfigValue( 'NeoWikiQueryLimits', [
			'default' => [ 'timeoutSeconds' => 30, 'maxRows' => 1 ],
			'expensive' => [ 'timeoutSeconds' => 30, 'maxRows' => 100 ],
		] );
	}

	/**
	 * Registers the SPARQL surfaces without a reachable store. The URL is never contacted: every
	 * test using this fails before any HTTP, on the permission gate or on an empty query.
	 */
	protected function configureAnUnreachableSparqlStore(): void {
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [ [ 'updateUrl' => 'https://sparql.invalid/store' ] ] );
		NeoWikiExtension::resetInstance();
	}

}
