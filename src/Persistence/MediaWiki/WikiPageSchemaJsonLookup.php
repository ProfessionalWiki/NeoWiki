<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use LogicException;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaContentUnavailableException;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;

class WikiPageSchemaJsonLookup implements SchemaJsonLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
	) {
	}

	public function getSchemaJson( Title $schemaPage ): string {
		$content = $this->getContent( $schemaPage );

		if ( $content === null ) {
			throw SchemaContentUnavailableException::forName( $schemaPage->getText() );
		}

		return $content->getText();
	}

	private function getContent( Title $schemaPage ): ?SchemaContent {
		$content = $this->pageContentFetcher->getPageContent( $schemaPage, $this->authority );

		if ( $content instanceof SchemaContent ) {
			return $content;
		}

		if ( $content === null ) {
			return null;
		}

		throw new LogicException( 'Unexpected content type: not a SchemaContent' );
	}

}
