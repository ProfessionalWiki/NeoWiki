<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

interface PropertyTypeLookup {

	/**
	 * Null when no extension registered the type. Callers must degrade rather than fail:
	 * stored data of an unregistered type is preserved and surfaced, not rejected.
	 */
	public function getType( string $typeName ): ?PropertyType;

}
