<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Page;

readonly class PageProperties {

	/**
	 * The page's title as prefixed text.
	 */
	public const string NAME = 'name';

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

	/**
	 * Empty when the page has no name to give. Properties come from a provider chain an extension can
	 * replace, so neither the key nor its type is guaranteed.
	 */
	public function getName(): string {
		$name = $this->get( self::NAME );

		return is_string( $name ) ? $name : '';
	}

}
