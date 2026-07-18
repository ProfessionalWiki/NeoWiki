<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use LogicException;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class WikiPageMappingLookup implements MappingLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
		private readonly MappingPersistenceDeserializer $mappingDeserializer,
	) {
	}

	public function getMapping( MappingName $name ): ?Mapping {
		$content = $this->getContent( $name );

		if ( $content === null ) {
			return null;
		}

		try {
			return $this->mappingDeserializer->deserialize( $name, $content->getText() );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
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
