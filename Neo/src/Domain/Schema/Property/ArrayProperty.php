<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;

class ArrayProperty extends PropertyDefinition {

	public function __construct(
		string $name,
		string $description,
		private readonly PropertyDefinition $itemDefinition,
	) {
		$this->assertIsValidItemType( $this->itemDefinition );

		parent::__construct(
			name: $name,
			description: $description,
			type: ValueType::Array,
			format: $itemDefinition->getFormat()
		);
	}

	private function assertIsValidItemType( PropertyDefinition $itemDefinition ): void {
		if ( $itemDefinition->getType() === ValueType::Array ) {
			throw new InvalidArgumentException( 'ArrayProperty cannot have an array item type' );
		}
	}

	public function getItemDefinition(): PropertyDefinition {
		return $this->itemDefinition;
	}

}
