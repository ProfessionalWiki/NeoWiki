<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use InvalidArgumentException;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\ValidationParams;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutContentValidator;
use StatusValue;
use Wikimedia\Message\ListType;
use Wikimedia\Message\MessageValue;

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
			$errors = $validator->getErrors();
			$details = [];

			foreach ( $errors as $pointer => $message ) {
				$details[] = MessageValue::new( 'neowiki-layout-invalid-detail' )->params( $pointer, $message );
			}

			// The details ride in this message rather than following as messages of their own:
			// the REST error envelope carries only the first message a Status holds, so a client
			// told "3 errors" and nothing else cannot act on it.
			$status->fatal(
				'neowiki-layout-invalid',
				count( $errors ),
				Message::listParam( $details, ListType::SEMICOLON )
			);
		}

		return $status;
	}

	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		$parserOutput->setContentHolderText( '' );
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
