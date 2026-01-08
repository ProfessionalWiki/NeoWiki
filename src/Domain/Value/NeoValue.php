<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

interface NeoValue {

	public function getType(): ValueType;

	public function toScalars(): mixed;

	public function isEmpty(): bool;

}
