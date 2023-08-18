<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaLookup;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class WikiPageSchemaLookup implements SchemaLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority
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
		$content = $this->pageContentFetcher->getPageContent(
			$schemaName->getText(),
			$this->authority,
			NeoWikiExtension::NS_SCHEMA
		);

		if ( $content instanceof SchemaContent ) {
			return $content;
		}

		return null;
	}

}
