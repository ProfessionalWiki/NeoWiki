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
		$schemaName = null;

		try {
			$schemaName = new SchemaName( $title->getText() );
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
		} elseif ( $schemaName !== null ) {
			$this->reportLabelTemplateErrors( $status, $schemaName, $content->getText() );
		}

		return $status;
	}

	/**
	 * Only for structurally valid content: the template is checked against the properties the Schema
	 * declares, which needs them read.
	 */
	private function reportLabelTemplateErrors( StatusValue $status, SchemaName $schemaName, string $json ): void {
		$extension = NeoWikiExtension::getInstance();
		$schema = $extension->getPersistenceSchemaDeserializer()->deserialize( $schemaName, $json );
		$errors = $extension->newLabelTemplateValidator()->errorsFor( $schema );

		if ( $errors !== [] ) {
			$this->reportValidationErrors(
				$status,
				'neowiki-schema-invalid',
				'neowiki-schema-invalid-detail',
				[ '/labelTemplate' => implode( ' ', $errors ) ]
			);
		}
	}

	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		$parserOutput->setContentHolderText( '' );
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
