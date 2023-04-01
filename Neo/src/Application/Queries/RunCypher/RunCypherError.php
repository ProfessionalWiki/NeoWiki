<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\RunCypher;

enum RunCypherError {

	case CONNECTION_FAILED;
	case NO_QUERY;
	case NOT_A_READ_QUERY;
	case INVALID_QUERY;

}
