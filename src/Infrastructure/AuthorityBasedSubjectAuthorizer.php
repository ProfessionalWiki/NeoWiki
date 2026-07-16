<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\SubjectPermissionHints;
use ProfessionalWiki\NeoWiki\Application\SubjectReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use Psr\Log\LoggerInterface;

class AuthorityBasedSubjectAuthorizer implements SubjectPermissionHints, SubjectWriteAuthorizer, SubjectReadAuthorizer {

	public function __construct(
		private Authority $authority,
		private TitleFactory $titleFactory,
		private LoggerInterface $logger
	) {
	}

	public function canCreateMainSubject( ?PageId $pageId ): bool {
		return $this->canEditPage( $pageId );
	}

	public function canCreateChildSubject( ?PageId $pageId ): bool {
		return $this->canEditPage( $pageId );
	}

	public function canEditSubject( ?PageId $pageId ): bool {
		return $this->canEditPage( $pageId );
	}

	private function canEditPage( ?PageId $pageId ): bool {
		$title = $this->newTitle( $pageId );

		if ( $title === null ) {
			return $this->authority->isAllowed( 'edit' );
		}

		// definitelyCan reads permissions from a replica and only peeks at the edit rate limit.
		return $this->authority->definitelyCan( 'edit', $title );
	}

	public function authorize( ?PageId $pageId ): bool {
		$title = $this->newTitle( $pageId );

		if ( $title === null ) {
			return $this->authority->isAllowed( 'edit' );
		}

		// authorizeWrite enforces page protection and blocks against the primary database, and
		// counts the write against the edit rate limit.
		return $this->authority->authorizeWrite( 'edit', $title );
	}

	public function authorizeRead( PageId $pageId ): bool {
		$title = $this->newTitle( $pageId );

		if ( $title === null ) {
			// Unlike writes, reads have no global-right fallback: content is only reachable
			// through a resolved page, so an unresolvable one has nothing to authorize.
			return false;
		}

		// authorizeRead runs the full per-title check (RIGOR_FULL), including the expensive
		// permission hook that extension ACLs use and that the quick checks skip.
		if ( !$this->authority->authorizeRead( 'read', $title ) ) {
			$this->logger->info( 'Denied read of page {page} to {user}', [
				'page' => $title->getPrefixedDBkey(),
				'user' => $this->authority->getUser()->getName(),
			] );
			return false;
		}

		return true;
	}

	/**
	 * Null when there is no page, or when the page could not be resolved (for instance because the
	 * Subject is not indexed). The write-side callers then fall back to the wiki-global edit
	 * right, so that authorization never fails open; such writes are a no-op, so page protection
	 * cannot be bypassed by suppressing the page. authorizeRead has no such fallback: content is
	 * only reachable through a resolved page, so an unresolvable one denies.
	 */
	private function newTitle( ?PageId $pageId ): ?Title {
		return $pageId === null ? null : $this->titleFactory->newFromID( $pageId->id );
	}

}
