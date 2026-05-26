<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Schema\Exception;

use RuntimeException;

class SchemaNotFoundException extends RuntimeException {

	public static function forName( string $schemaName ): self {
		return new self( 'Schema not found: ' . $schemaName );
	}

}
