<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\Content;

use Content;
use MediaWiki\Content\Renderer\ContentParseParams;
use ParserOutput;

class SubjectContentHandler extends \JsonContentHandler {

	protected function getContentClass(): string {
		return SubjectContent::class;
	}

	public function makeEmptyContent(): SubjectContent {
		return new SubjectContent( '{}' );
	}

	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		$parserOutput->setText( '' );
	}

}
