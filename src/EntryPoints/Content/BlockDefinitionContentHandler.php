<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

class BlockDefinitionContentHandler extends \JsonContentHandler {

	protected function getContentClass(): string {
		return BlockDefinitionContent::class;
	}

	public function makeEmptyContent(): BlockDefinitionContent {
		return new BlockDefinitionContent( <<<JSON
{
	"type": "wikitext",
	"description": ""
}
JSON
		);
	}

}
