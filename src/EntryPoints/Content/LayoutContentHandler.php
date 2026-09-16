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
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutContentValidator;
use StatusValue;

class LayoutContentHandler extends JsonContentHandler {

	use ReportsValidationErrors;

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
			$this->reportValidationErrors(
				$status,
				'neowiki-layout-invalid',
				'neowiki-layout-invalid-detail',
				$validator->getErrors()
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
