<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat;

use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

interface ValueFormat {

	public function getFormatName(): string;

	public function getValueType(): ValueType;

}
