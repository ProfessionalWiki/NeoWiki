<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\SubjectPermissionHints;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class AuthorityBasedSubjectAuthorizer implements SubjectPermissionHints, SubjectWriteAuthorizer {

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

	/**
	 * Null when there is no page, or when the page could not be resolved (for instance because the
	 * Subject is not indexed). Callers then fall back to the wiki-global edit right, so that
	 * authorization never fails open. Such writes are a no-op, so page protection cannot be
	 * bypassed by suppressing the page.
	 */
	private function newTitle( ?PageId $pageId ): ?Title {
		return $pageId === null ? null : $this->titleFactory->newFromID( $pageId->id );
	}

}
