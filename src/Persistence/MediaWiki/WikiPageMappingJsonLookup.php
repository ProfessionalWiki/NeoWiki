<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use LogicException;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class WikiPageMappingJsonLookup implements MappingJsonLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
	) {
	}

	public function getMappingJson( MappingName $name ): ?string {
		return $this->getContent( $name )?->getText();
	}

	private function getContent( MappingName $name ): ?MappingContent {
		$content = $this->pageContentFetcher->getPageContent(
			$name->getText(),
			$this->authority,
			NeoWikiExtension::NS_MAPPING
		);

		if ( $content instanceof MappingContent ) {
			return $content;
		}

		if ( $content === null ) {
			return null;
		}

		throw new LogicException( 'Unexpected content type: not a MappingContent' );
	}

}
