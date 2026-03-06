<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class ViewContentHandler extends JsonContentHandler {

	protected function getContentClass(): string {
		return ViewContent::class;
	}

	public function makeEmptyContent(): ViewContent {
		return new ViewContent( <<<JSON
{
	"schema": "",
	"type": ""
}
JSON
		);
	}

	public function canBeUsedOn( Title $title ): bool {
		return $title->getNamespace() === NeoWikiExtension::NS_VIEW;
	}

}
