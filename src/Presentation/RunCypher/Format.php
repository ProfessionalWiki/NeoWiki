<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation\RunCypher;

use ValueError;

enum Format: string {

	case DEFAULT = 'default';
	case MEDIAWIKI_TABLE = 'table';
	case TABULATOR_TABLE = 'editableTable';
	case DEBUG_JSON = 'json';

	public static function fromString( string $format ): self {
		try {
			return self::from( $format );
		} catch ( ValueError ) {
			return self::DEFAULT;
		}
	}

}
