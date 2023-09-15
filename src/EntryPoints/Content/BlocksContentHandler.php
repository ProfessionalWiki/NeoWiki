<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

class BlocksContentHandler extends \JsonContentHandler {

	protected function getContentClass(): string {
		return BlockDefinitionContent::class;
	}

	public function makeEmptyContent(): BlockDefinitionContent {
		return new BlockDefinitionContent( <<<JSON
{
	"blocks": {

	}
}
JSON
		);
	}

}
