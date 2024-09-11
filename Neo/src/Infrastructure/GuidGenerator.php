<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

interface GuidGenerator {

	public function generate(): string;

}
