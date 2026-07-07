<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class AuthorityBasedSubjectAuthorizer implements SubjectAuthorizer {

	public function __construct(
		private Authority $authority,
		private TitleFactory $titleFactory
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

	public function authorizeEdit( ?PageId $pageId ): bool {
		$title = $pageId === null ? null : $this->titleFactory->newFromID( $pageId->id );

		if ( $title === null ) {
			// The page could not be resolved (e.g. the Subject is not indexed). Fall back to the
			// wiki-global edit right so authorization never fails open; the write is a no-op in this
			// case, so page-level protection cannot be bypassed.
			return $this->authority->isAllowed( 'edit' );
		}

		// authorizeWrite (RIGOR_SECURE) enforces page protection and blocks against the primary
		// database and counts the write against the edit rate limit, unlike the can* checks.
		return $this->authority->authorizeWrite( 'edit', $title );
	}

	private function canEditPage( ?PageId $pageId ): bool {
		$title = $pageId === null ? null : $this->titleFactory->newFromID( $pageId->id );

		if ( $title === null ) {
			// The page could not be resolved (e.g. the Subject is not indexed). Fall back to the
			// wiki-global edit right so authorization never fails open, while page-level protection
			// is still enforced for every Subject whose page can be resolved.
			return $this->authority->isAllowed( 'edit' );
		}

		return $this->authority->definitelyCan( 'edit', $title );
	}

}
