<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\LayoutLookup;
use ProfessionalWiki\NeoWiki\Domain\Layout\Layout;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Psr\Log\LoggerInterface;

class WikiPageLayoutLookup implements LayoutLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
		private readonly LayoutPersistenceDeserializer $layoutDeserializer,
		private readonly TitleFactory $titleFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getLayout( LayoutName $layoutName ): ?Layout {
		$title = $this->titleFactory->newFromText( $layoutName->getText(), NeoWikiExtension::NS_LAYOUT );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// The audience check inside the content fetcher filters revision deletion only; this
		// is the sole per-title read gate on the Layout read path. Denial is null, the same
		// as an absent Layout (#1046).
		if ( !$this->authority->authorizeRead( 'read', $title ) ) {
			$this->logger->info( 'Denied read of page {page} to {user}', [
				'page' => $title->getPrefixedDBkey(),
				'user' => $this->authority->getUser()->getName(),
			] );
			return null;
		}

		$content = $this->getContent( $title );

		if ( $content === null ) {
			return null;
		}

		try {
			return $this->layoutDeserializer->deserialize( $layoutName, $content->getText() );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
	}

	private function getContent( Title $title ): ?LayoutContent {
		$content = $this->pageContentFetcher->getPageContent(
			$title,
			$this->authority,
			NeoWikiExtension::NS_LAYOUT
		);

		if ( $content instanceof LayoutContent ) {
			return $content;
		}

		if ( $content === null ) {
			return null;
		}

		throw new \LogicException( 'Unexpected content type: not a LayoutContent' );
	}

}
