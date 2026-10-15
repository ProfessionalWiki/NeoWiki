<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Subject\Exception;

use Exception;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class SubjectIdInUseException extends Exception {

	public function __construct(
		public readonly SubjectId $subjectId,
	) {
		parent::__construct( "The Subject ID {$subjectId->text} is in use by another Subject" );
	}

}
