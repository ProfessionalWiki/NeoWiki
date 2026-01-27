<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

use OutOfBoundsException;

interface PropertyTypeLookup {

	public function getType( string $typeName ): ?PropertyType;

	/**
	 * @throws OutOfBoundsException
	 */
	public function getTypeOrThrow( string $typeName ): PropertyType;

}
