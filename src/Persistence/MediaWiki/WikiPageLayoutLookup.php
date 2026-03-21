<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Application\LayoutLookup;
use ProfessionalWiki\NeoWiki\Domain\Layout\Layout;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class WikiPageLayoutLookup implements LayoutLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
		private readonly LayoutPersistenceDeserializer $layoutDeserializer,
	) {
	}

	public function getLayout( LayoutName $layoutName ): ?Layout {
		$content = $this->getContent( $layoutName );

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

	private function getContent( LayoutName $layoutName ): ?LayoutContent {
		$content = $this->pageContentFetcher->getPageContent(
			$layoutName->getText(),
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
