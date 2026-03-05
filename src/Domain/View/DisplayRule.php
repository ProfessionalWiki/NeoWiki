<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\View;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;

readonly class DisplayRule {

	/**
	 * @param array<string, mixed> $displayAttributes
	 */
	public function __construct(
		private PropertyName $property,
		private array $displayAttributes,
	) {
	}

	public function getProperty(): PropertyName {
		return $this->property;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getDisplayAttributes(): array {
		return $this->displayAttributes;
	}

}
