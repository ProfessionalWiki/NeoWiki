<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

class BlocksContentHandler extends \JsonContentHandler {

	protected function getContentClass(): string {
		return BlocksContent::class;
	}

	public function makeEmptyContent(): BlocksContent {
		return BlocksContent::newEmpty();
	}

}
