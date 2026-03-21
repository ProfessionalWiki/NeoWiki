<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class LayoutContentHandler extends JsonContentHandler {

	protected function getContentClass(): string {
		return LayoutContent::class;
	}

	public function makeEmptyContent(): LayoutContent {
		return new LayoutContent( <<<JSON
{
	"schema": "",
	"type": ""
}
JSON
		);
	}

	public function canBeUsedOn( Title $title ): bool {
		return $title->getNamespace() === NeoWikiExtension::NS_LAYOUT;
	}

}
