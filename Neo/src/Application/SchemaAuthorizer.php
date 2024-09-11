<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application;

interface SchemaAuthorizer {

	public function canCreateSchema(): bool;

}
