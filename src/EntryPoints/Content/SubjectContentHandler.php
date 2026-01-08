<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use ParserOutput;

class SubjectContentHandler extends JsonContentHandler {

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
		$parserOutput->setRawText( '' );
	}

}
