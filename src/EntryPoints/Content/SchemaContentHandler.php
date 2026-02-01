<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Title\Title;
use ParserOutput;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class SchemaContentHandler extends JsonContentHandler {

	protected function getContentClass(): string {
		return SchemaContent::class;
	}

	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		$parserOutput->setRawText( '' );
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

	public function canBeUsedOn( Title $title ): bool {
		return $title->getNamespace() === NeoWikiExtension::NS_SCHEMA;
	}

}
