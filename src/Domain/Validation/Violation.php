<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;

readonly class Violation {

	public function __construct(
		public ?PropertyName $propertyName,
		public string $code,
		public array $args = [],
		public ?int $valuePartIndex = null,
	) {
	}

	public function withPropertyName( PropertyName $name ): self {
		return new self(
			propertyName: $name,
			code: $this->code,
			args: $this->args,
			valuePartIndex: $this->valuePartIndex,
		);
	}

}
