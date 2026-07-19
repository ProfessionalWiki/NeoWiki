<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use LogicException;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
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
		private readonly TitleParser $titleParser,
	) {
	}

	public function getMapping( MappingName $name ): ?Mapping {
		$content = $this->getContent( $name );

		if ( $content === null ) {
			return null;
		}

		try {
			return $this->mappingDeserializer->deserialize( $this->canonicalName( $name ), $content->getText() );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * The requested name normalized to its Mapping page's canonical title text — MediaWiki capitalizes
	 * the first letter — so a projection requested as "eDM" and one requested as "EDM" (the same page)
	 * mint the same projector, serializer, and named-graph IRI. The content fetch already succeeded, so
	 * the title parses; the guard is defensive.
	 */
	private function canonicalName( MappingName $name ): MappingName {
		try {
			return new MappingName(
				$this->titleParser->parseTitle( $name->getText(), NeoWikiExtension::NS_MAPPING )->getText()
			);
		}
		catch ( MalformedTitleException ) {
			return $name;
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
