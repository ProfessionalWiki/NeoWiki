<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use Ramsey\Uuid\Uuid;

class ProductionGuidGenerator implements GuidGenerator {

	public function generate(): string {
		return Uuid::uuid7()->toString();
	}
}
