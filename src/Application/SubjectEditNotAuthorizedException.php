<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use RuntimeException;

class SubjectEditNotAuthorizedException extends RuntimeException {

	public function __construct( string $message = 'You do not have the necessary permissions to edit this subject' ) {
		parent::__construct( $message );
	}

}
