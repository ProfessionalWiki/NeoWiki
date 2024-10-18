<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;

class WikiPageSchemaLookup implements SchemaLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
		private readonly SchemaPersistenceDeserializer $schemaDeserializer,
	) {
	}

	public function getSchema( SchemaName $schemaId ): ?Schema {
		$content = $this->getContent( $schemaId );

		if ( $content === null ) {
			return null;
		}

		try {
			return $this->schemaDeserializer->deserialize( $schemaId, $content->getText() );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
	}

	private function getContent( SchemaName $schemaName ): ?SchemaContent {
		$content = $this->pageContentFetcher->getPageContent(
			$schemaName->getText(),
			$this->authority,
			NeoWikiExtension::NS_SCHEMA
		);

		if ( $content instanceof SchemaContent ) {
			return $content;
		}

		if ( $content === null ) {
			return null;
		}

		throw new \LogicException( 'Unexpected content type: not a SchemaContent' );
	}

}
