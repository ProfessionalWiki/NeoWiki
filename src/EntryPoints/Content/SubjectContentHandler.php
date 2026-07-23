<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Title\Title;
use MediaWiki\Parser\ParserOutput;

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

	/**
	 * Subject content is only ever valid inside the dedicated subject slot, whose fixed content model is
	 * enforced by the slot role rather than by this method. It is never a page's main content model, so
	 * it must not be selectable as one.
	 */
	public function canBeUsedOn( Title $title ): bool {
		return false;
	}

}
