<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class WikiPageMappingLookup implements MappingLookup {

	public function __construct(
		private readonly PublishedPageJsonLookup $jsonLookup,
		private readonly MappingPersistenceDeserializer $mappingDeserializer,
		private readonly TitleFactory $titleFactory,
		private readonly PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function getMapping( MappingName $name ): ?Mapping {
		$title = $this->titleFactory->newFromText( $name->getText(), NeoWikiExtension::NS_MAPPING );

		// A namespace prefix in the name takes the title out of the Mapping namespace.
		if ( $title === null || $title->getNamespace() !== NeoWikiExtension::NS_MAPPING || !$title->exists() ) {
			return null;
		}

		// The JSON lookup applies no per-title read check (its revision audience check filters
		// revision deletion only), so this is the sole read gate on the Mapping read path. It
		// must also run before the cache: the cached value is user-independent mapping content,
		// and a cache hit must not serve a Mapping whose page the user may not read (#1046).
		if ( !$this->readAuthorizer->authorizeReadByPageTitle( $title ) ) {
			return null;
		}

		$json = $this->jsonLookup->getJson( $title );

		if ( $json === null ) {
			return null;
		}

		return $this->deserialize( $title, $json );
	}

	/**
	 * Names the Mapping after its page rather than the request: MediaWiki capitalizes a title's
	 * first letter, so a projection requested as "eDM" and one requested as "EDM" (the same page)
	 * mint the same projector, serializer, and named-graph IRI. A page whose title is no usable
	 * Mapping name, such as one imported under the reserved "Native", yields null like content that
	 * does not deserialize.
	 */
	private function deserialize( Title $title, string $json ): ?Mapping {
		try {
			return $this->mappingDeserializer->deserialize( new MappingName( $title->getText() ), $json );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
	}

}
