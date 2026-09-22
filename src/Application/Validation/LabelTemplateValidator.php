<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

/**
 * Whether a Schema's label template can label its Subjects: each placeholder must name a property of
 * the Schema whose values read as text, which relations and booleans do not. A property of a type not
 * registered on the wiki passes: it reads as nothing, and refusing it would block every save of a Schema
 * whose extension was uninstalled.
 */
readonly class LabelTemplateValidator {

	private const array USABLE_VALUE_TYPES = [ ValueType::String, ValueType::Number, ValueType::MonolingualText ];

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
	) {
	}

	/**
	 * @return string[] One sentence per problem, for the Schema's author
	 */
	public function errorsFor( Schema $schema ): array {
		$names = $schema->getLabelTemplate()?->getPropertyNames();

		if ( $names === null ) {
			return [];
		}

		if ( $names === [] ) {
			return [
				'The label template names no property, so it would give every Subject the same label. '
				. 'Name a property in braces, such as {Title}.'
			];
		}

		return $this->placeholderErrors( $names, $schema );
	}

	/**
	 * @param PropertyName[] $names
	 * @return string[]
	 */
	private function placeholderErrors( array $names, Schema $schema ): array {
		$errors = [];

		foreach ( $names as $name ) {
			$error = $this->placeholderError( $name, $schema );

			if ( $error !== null ) {
				$errors[] = $error;
			}
		}

		return $errors;
	}

	private function placeholderError( PropertyName $name, Schema $schema ): ?string {
		if ( !$schema->hasProperty( $name ) ) {
			return 'The label template names "' . $name->text . '", which is not a property of this Schema.';
		}

		$typeName = $schema->getProperty( $name )->getPropertyType();
		$valueType = $this->propertyTypeLookup->getType( $typeName )?->getValueType();

		if ( $valueType !== null && !in_array( $valueType, self::USABLE_VALUE_TYPES, true ) ) {
			return 'The label template names "' . $name->text . '", a ' . $typeName
				. ' property. Only properties holding text or numbers can appear in a label.';
		}

		return null;
	}

}
