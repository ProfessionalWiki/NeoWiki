<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

/**
 * Fixme: this is the wrong NS
 */
interface SchemaAuthorizer {
	public function canCreateSchema(): bool;
}
