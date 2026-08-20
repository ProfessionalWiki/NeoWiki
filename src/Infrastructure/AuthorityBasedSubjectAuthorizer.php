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

	public function canCreateMainSubject( PageId $pageId ): bool {
		return $this->canEditPage( $pageId );
	}

	public function canCreateChildSubject( PageId $pageId ): bool {
		return $this->canEditPage( $pageId );
	}

	public function canEditSubject( PageId $pageId ): bool {
		return $this->canEditPage( $pageId );
	}

	private function canEditPage( PageId $pageId ): bool {
		$title = $this->newTitle( $pageId );

		// definitelyCan reads permissions from a replica and only peeks at the edit rate limit.
		return $title !== null && $this->authority->definitelyCan( 'edit', $title );
	}

	public function authorize( PageId $pageId ): bool {
		$title = $this->newTitle( $pageId );

		// authorizeWrite enforces page protection and blocks against the primary database, and
		// counts the write against the edit rate limit.
		return $title !== null && $this->authority->authorizeWrite( 'edit', $title );
	}

	/**
	 * Null when the Subject is on no page this wiki has. Every right a Subject write needs is a right on
	 * the page holding it, so with no page there is nothing to check and nothing to allow — the write is
	 * refused rather than measured against the wiki-global edit right (ADR 32). Creating a Subject is
	 * authorized against the page the request names, so it is unaffected.
	 */
	private function newTitle( PageId $pageId ): ?Title {
		return $this->titleFactory->newFromID( $pageId->id );
	}

}
