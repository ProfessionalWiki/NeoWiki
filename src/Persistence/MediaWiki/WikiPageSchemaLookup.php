<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaContentUnavailableException;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class WikiPageSchemaLookup implements SchemaLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
		private readonly SchemaPersistenceDeserializer $schemaDeserializer,
	) {
	}

	/**
	 * Returns null when the Schema page's content is not a valid Schema, and throws when that content
	 * could not be read at all, so that a caching decorator can tell the durable outcome from the
	 * transient one. See {@see SchemaContentUnavailableException}.
	 *
	 * @throws SchemaContentUnavailableException
	 */
	public function getSchema( SchemaName $schemaName ): ?Schema {
		$content = $this->getContent( $schemaName );

		if ( $content === null ) {
			throw SchemaContentUnavailableException::forName( $schemaName->getText() );
		}

		try {
			return $this->schemaDeserializer->deserialize( $schemaName, $content->getText() );
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
