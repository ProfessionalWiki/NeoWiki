<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

class SubjectContentHandler extends \JsonContentHandler {

	protected function getContentClass(): string {
		return SubjectContent::class;
	}

	public function makeEmptyContent(): SubjectContent {
		return new SubjectContent( '{}' );
	}

}
