<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use InvalidArgumentException;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\ValidationParams;
use MediaWiki\Title\Title;
use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutContentValidator;
use StatusValue;

class LayoutContentHandler extends JsonContentHandler {

	protected function getContentClass(): string {
		return LayoutContent::class;
	}

	public function validateSave( Content $content, ValidationParams $validationParams ): StatusValue {
		$status = parent::validateSave( $content, $validationParams );

		if ( !$status->isOK() ) {
			return $status;
		}

		$title = Title::newFromPageIdentity( $validationParams->getPageIdentity() );

		try {
			new LayoutName( $title->getText() );
		} catch ( InvalidArgumentException $exception ) {
			$status->fatal( 'neowiki-layout-name-invalid', $exception->getMessage() );
		}

		$validator = LayoutContentValidator::newInstance();

		if ( !$validator->validate( $content->getText() ) ) {
			$status->fatal( 'neowiki-layout-invalid', count( $validator->getErrors() ) );

			foreach ( $validator->getErrors() as $pointer => $message ) {
				$status->fatal( 'neowiki-layout-invalid-detail', $pointer, $message );
			}
		}

		return $status;
	}

	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		$parserOutput->setRawText( '' );
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
		return NamespaceContentModels::forNamespace( $title->getNamespace() ) === LayoutContent::CONTENT_MODEL_ID;
	}

}
