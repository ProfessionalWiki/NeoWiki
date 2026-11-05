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
use ProfessionalWiki\NeoWiki\Domain\Schema\DependentSubjectSchemaRules;
use ProfessionalWiki\NeoWiki\Domain\Schema\InvalidFieldException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyNameRules;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaContentValidator;
use ProfessionalWiki\NeoWiki\Presentation\PropertyNameProblemMessage;
use StatusValue;
use Wikimedia\Message\MessageValue;

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
			$schemaName = new SchemaName( $title->getText() );
		} catch ( InvalidArgumentException $exception ) {
			$schemaName = null;
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

		if ( !$status->isOK() || $schemaName === null ) {
			return $status;
		}

		$propertyDefinitions = $this->propertyDefinitions( $content->getText() );
		$this->checkPropertyNames( $status, $propertyDefinitions );

		if ( $status->isOK() ) {
			$this->checkPropertyDefinitionsAreReadable( $status, $propertyDefinitions );
			$this->checkDependentSubjectRules(
				$status,
				NeoWikiExtension::getInstance()->getPersistenceSchemaDeserializer()->deserialize( $schemaName, $content->getText() )
			);
		}

		return $status;
	}

	/**
	 * Read from the JSON rather than from the Schema it holds, which leaves out integer names
	 * and the Property Definitions it cannot read.
	 *
	 * @return array<array-key, array<string, mixed>>
	 */
	private function propertyDefinitions( string $json ): array {
		/** @var array{propertyDefinitions: array<array-key, array<string, mixed>>} $schema */
		$schema = json_decode( $json, true );

		return $schema['propertyDefinitions'];
	}

	/**
	 * @param array<array-key, array<string, mixed>> $propertyDefinitions
	 */
	private function checkPropertyNames( StatusValue $status, array $propertyDefinitions ): void {
		$problems = PropertyNameRules::problemsInSchema( array_map( strval( ... ), array_keys( $propertyDefinitions ) ) );

		if ( $problems !== [] ) {
			$this->reportErrors(
				$status,
				'neowiki-schema-invalid',
				array_map( PropertyNameProblemMessage::from( ... ), $problems )
			);
		}
	}

	/**
	 * @param array<array-key, array<string, mixed>> $propertyDefinitions
	 */
	private function checkPropertyDefinitionsAreReadable( StatusValue $status, array $propertyDefinitions ): void {
		$errors = $this->readingErrors( $propertyDefinitions );

		if ( $errors !== [] ) {
			$this->reportErrors( $status, 'neowiki-schema-invalid', $errors );
		}
	}

	/**
	 * @param array<array-key, array<string, mixed>> $propertyDefinitions
	 *
	 * @return MessageValue[]
	 */
	private function readingErrors( array $propertyDefinitions ): array {
		$propertyTypes = NeoWikiExtension::getInstance()->getPropertyTypeLookup();
		$errors = [];

		foreach ( $propertyDefinitions as $propertyName => $propertyDefinition ) {
			$pointer = '/propertyDefinitions/' . self::pointerSegment( (string)$propertyName );

			try {
				PropertyDefinition::fromJson( $propertyDefinition, $propertyTypes );
			} catch ( InvalidFieldException $exception ) {
				$errors[] = self::readingError( $pointer . '/' . $exception->field, $exception->reason );
			} catch ( InvalidArgumentException ) {
				$errors[] = self::readingError( $pointer, 'its Property Type cannot use this Property Definition' );
			}
		}

		return $errors;
	}

	private static function pointerSegment( string $key ): string {
		return str_replace( [ '~', '/' ], [ '~0', '~1' ], $key );
	}

	/**
	 * Plain text, as the entry quotes a property name and a value from the Schema.
	 */
	private static function readingError( string $pointer, string $reason ): MessageValue {
		return MessageValue::new( 'neowiki-schema-invalid-detail' )->plaintextParams( $pointer, $reason );
	}

	private function checkDependentSubjectRules( StatusValue $status, Schema $schema ): void {
		$rules = new DependentSubjectSchemaRules( NeoWikiExtension::getInstance()->getSchemaResolver() );

		if ( $rules->changesDeclaration( $schema ) ) {
			$status->fatal( 'neowiki-schema-dependence-unchangeable' );
			return;
		}

		$mixingProperties = $rules->propertiesMixingTargetSchemas( $schema );

		if ( $mixingProperties !== [] ) {
			$status->fatal(
				'neowiki-schema-relation-targets-mixed',
				Message::listParam( $mixingProperties ),
				count( $mixingProperties )
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
