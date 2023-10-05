<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Infrastructure\SchemaAuthorizer;

class SucceedingSchemaAuthorizer implements SchemaAuthorizer {

	public function canCreateSchema(): bool {
		return true;
	}

}
