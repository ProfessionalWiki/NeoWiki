<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

use OutOfBoundsException;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

readonly class PropertyTypeToValueType {

	public function __construct(
		private PropertyTypeRegistry $registry
	) {
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function lookup( string $propertyType ): ValueType {
		return $this->registry->getTypeOrThrow( $propertyType )->getValueType();
	}

}
