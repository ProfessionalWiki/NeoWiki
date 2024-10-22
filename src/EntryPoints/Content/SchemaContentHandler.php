<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\Content;

use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;

class SchemaContentHandler extends JsonContentHandler {

	protected function getContentClass(): string {
		return SchemaContent::class;
	}

	public function makeEmptyContent(): SchemaContent {
		return new SchemaContent( <<<JSON
{
	"propertyDefinitions": {

	}
}
JSON
		);
	}

	public function canBeUsedOn( Title $title ) {
		return $title->getNamespace() === NeoWikiExtension::NS_SCHEMA;
	}

}
