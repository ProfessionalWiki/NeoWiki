<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Content;

use InvalidArgumentException;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\ValidationParams;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaContentValidator;
use StatusValue;

class SchemaContentHandler extends JsonContentHandler {

	use ReportsValidationErrors;

	protected function getContentClass(): string {
		return SchemaContent::class;
	}

	public function validateSave( Content $content, ValidationParams $validationParams ): StatusValue {
		$status = parent::validateSave( $content, $validationParams );

		if ( !$status->isOK() ) {
			return $status;
		}

		$title = Title::newFromPageIdentity( $validationParams->getPageIdentity() );

		try {
			new SchemaName( $title->getText() );
		} catch ( InvalidArgumentException $exception ) {
			$status->fatal( 'neowiki-schema-name-invalid', $exception->getMessage() );
		}

		$validator = SchemaContentValidator::newInstance();

		if ( !$validator->validate( $content->getText() ) ) {
			$this->reportValidationErrors(
				$status,
				'neowiki-schema-invalid',
				'neowiki-schema-invalid-detail',
				$validator->getErrors()
			);
		}

		return $status;
	}

	/**
	 * The Schema itself renders client-side. A page that does not match the format, which an import can store,
	 * says so and joins the tracking category.
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		$parserOutput->setContentHolderText( '' );

		$validator = SchemaContentValidator::newInstance();

		if ( $validator->validate( $content->getText() ) ) {
			return;
		}

		MediaWikiServices::getInstance()->getTrackingCategories()->addTrackingCategory(
			$parserOutput,
			'neowiki-schema-invalid-category',
			$cpoParams->getPage()
		);

		if ( $cpoParams->getGenerateHtml() ) {
			$parserOutput->setContentHolderText( $this->invalidSchemaNotice( $validator->getErrors() ) );
			$parserOutput->addModuleStyles( [ 'mediawiki.codex.messagebox.styles' ] );
		}
	}

	/**
	 * @param array<string, string> $errors Validation message by JSON pointer
	 */
	private function invalidSchemaNotice( array $errors ): string {
		$items = '';

		foreach ( $errors as $pointer => $message ) {
			$items .= Html::element(
				'li',
				[],
				wfMessage( 'neowiki-schema-invalid-detail' )->params( $pointer, $message )->inContentLanguage()->text()
			);
		}

		return Html::warningBox(
			wfMessage( 'neowiki-schema-invalid-notice' )->numParams( count( $errors ) )->inContentLanguage()->escaped()
			. Html::rawElement( 'ul', [], $items )
		);
	}

	public function makeEmptyContent(): SchemaContent {
		return new SchemaContent( <<<JSON
{
	"propertyDefinitions": {

	}
}
JSON
		);
	}

	public function canBeUsedOn( Title $title ): bool {
		return $title->getNamespace() === NeoWikiExtension::NS_SCHEMA;
	}

}
