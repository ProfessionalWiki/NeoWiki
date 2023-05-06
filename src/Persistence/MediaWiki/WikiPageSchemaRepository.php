<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaRepository;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;

class WikiPageSchemaRepository implements SchemaRepository {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
	) {
	}

	public function getSchema( SchemaId $schemaId ): ?Schema {
		$content = $this->getContent( $schemaId );

		if ( $content === null ) {
			return null;
		}

		try {
			return ( new SchemaDeserializer() )->deserialize( $schemaId, $content->getText() );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
	}

	private function getContent( SchemaId $schemaName ): ?SchemaContent {
		$content = $this->pageContentFetcher->getPageContent( $schemaName->getText(), NS_NEOWIKI_SCHEMA );

		if ( $content instanceof SchemaContent ) {
			return $content;
		}

		return null;
	}

}
