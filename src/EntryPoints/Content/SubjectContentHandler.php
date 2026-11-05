<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\Title;
use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\NeoWiki\Infrastructure\ParserPageDependencyRecorder;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

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
		$parserOutput->setContentHolderText( '' );

		$schemas = $content instanceof SubjectContent ? $content->getSchemaReferences() : [];

		if ( $schemas === [] || !$this->anonymousUsersCanRead( $cpoParams->getPage() ) ) {
			return;
		}

		$recorder = ParserPageDependencyRecorder::forParserOutput( $parserOutput, $cpoParams->getPage() );

		foreach ( $schemas as $schema ) {
			$recorder->recordDependencyOnSchema( $schema );
		}
	}

	/**
	 * Link tables are public, so like other data derived from a page's Subjects, their Schemas are
	 * recorded only where anonymous users can read the page.
	 */
	private function anonymousUsersCanRead( PageReference $page ): bool {
		return NeoWikiExtension::getInstance()
			->newPageReadAuthorizer( MediaWikiServices::getInstance()->getUserFactory()->newAnonymous() )
			->authorizeReadByPageTitle( Title::newFromPageReference( $page ) );
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
