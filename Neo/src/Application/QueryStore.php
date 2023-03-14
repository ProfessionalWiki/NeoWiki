<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Subject;

interface QueryStore {

	public function saveSubject( Subject $subject ): void;
	
}
