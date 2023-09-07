<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat;

use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

/**
 * TODO: rename to ValueFormat once the deprecated enum is gone
 */
interface ValueFormatDefinition {

	public function getFormatName(): string;

	public function getValueType(): ValueType;

}
