<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use Psr\Log\LoggerInterface;

class AuthorityBasedPageReadAuthorizer implements PageReadAuthorizer {

	public function __construct(
		private Authority $authority,
		private TitleFactory $titleFactory,
		private LoggerInterface $logger
	) {
	}

	public function authorizeReadByPageId( PageId $pageId ): bool {
		$title = $this->titleFactory->newFromID( $pageId->id );

		// Unlike the write side, reads have no global-right fallback: content is only reachable
		// through a resolved page, so an unresolvable one has nothing to authorize.
		return $title !== null && $this->authorizeReadByPageTitle( $title );
	}

	public function authorizeReadByPageTitle( Title $title ): bool {
		// authorizeRead runs the full per-title check (RIGOR_FULL), including the expensive
		// permission hook that extension ACLs use and that the quick checks skip.
		if ( $this->authority->authorizeRead( 'read', $title ) ) {
			return true;
		}

		$this->logger->info( 'Denied read of page {page} to {user}', [
			'page' => $title->getPrefixedDBkey(),
			'user' => $this->authority->getUser()->getName(),
		] );

		return false;
	}

}
