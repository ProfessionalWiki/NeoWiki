<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

class SubjectProperties {

	/**
	 * @var array<string, mixed>
	 */
	private readonly array $map;

	/**
	 * @param array<string, mixed> $map
	 */
	public function __construct( array $map ) {
		$this->map = $this->arrayFilter( $map );
	}

	/**
	 * @param array<array-key, mixed> $input
	 * @return array<string, mixed>
	 * @psalm-suppress all
	 */
	public function arrayFilter( array $input ): array {
		return array_filter( $input, function( $value ) {
			if ( is_array( $value ) ) {
				return count( $value ) > 0;
			}
			if ( is_string( $value ) ) {
				return $value !== '';
			}
			return $value !== null;
		} );
	}
	
	/**
	 * @param array<string, array> $patch Property name to list of new values
	 */
	public function applyPatch( array $patch ): self {
		$newMap = $this->map;

		foreach ( $patch as $propertyName => $values ) {
			$newMap[$propertyName] = $values;
		}

		return new self( $newMap );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function asMap(): array {
		return $this->map;
	}

}
