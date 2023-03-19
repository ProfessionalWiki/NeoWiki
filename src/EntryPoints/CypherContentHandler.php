<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

class CypherContentHandler extends \TextContentHandler {

	protected function getContentClass(): string {
		return CypherContent::class;
	}

	public function makeEmptyContent(): CypherContent {
		return new CypherContent( '' );
	}

}
