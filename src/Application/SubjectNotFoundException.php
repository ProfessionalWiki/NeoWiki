<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use RuntimeException;

class SubjectNotFoundException extends RuntimeException {

	public static function forId( SubjectId $id ): self {
		return new self( 'Subject not found: ' . $id->text );
	}

}
