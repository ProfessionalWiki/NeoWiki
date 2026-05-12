<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

class PageProperties {

	/**
	 * @param array<string, mixed> $properties
	 */
	public function __construct(
		private array $properties = [],
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function asArray(): array {
		return $this->properties;
	}

	public function get( string $key ): mixed {
		return $this->properties[$key] ?? null;
	}

}
