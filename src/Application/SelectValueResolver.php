<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;

readonly class SelectValueResolver {

	/**
	 * Resolves a raw input value (id, label, or {id,label} object) to the canonical option ID.
	 * Throws on mismatch or unknown.
	 *
	 * @param string|array<string,string> $raw
	 */
	public function resolve( SelectProperty $property, string|array $raw ): string {
		if ( is_array( $raw ) ) {
			return $this->resolveFromObject( $property, $raw );
		}

		return $this->resolveFromScalar( $property, $raw );
	}

	private function resolveFromScalar( SelectProperty $property, string $raw ): string {
		$byId = $this->findById( $property, $raw );
		if ( $byId !== null ) {
			return $byId->getId();
		}

		return $this->resolveByLabel( $property, $raw );
	}

	private function resolveByLabel( SelectProperty $property, string $label ): string {
		$normalized = strtolower( trim( $label ) );

		foreach ( $property->getOptions() as $option ) {
			if ( $option->normalizedLabel() === $normalized ) {
				return $option->getId();
			}
		}

		throw new InvalidArgumentException( "No select option matches \"$label\"" );
	}

	/**
	 * @param array<string,string> $raw
	 */
	private function resolveFromObject( SelectProperty $property, array $raw ): string {
		if ( !isset( $raw['id'] ) && !isset( $raw['label'] ) ) {
			throw new InvalidArgumentException( 'Select value must have id or label' );
		}

		$id = $raw['id'] ?? null;
		$label = $raw['label'] ?? null;

		if ( $id !== null && $label !== null ) {
			$matched = $this->findById( $property, $id );
			if ( $matched === null || $matched->normalizedLabel() !== strtolower( trim( $label ) ) ) {
				throw new InvalidArgumentException( "Select value id/label mismatch for \"$id\" / \"$label\"" );
			}
			return $matched->getId();
		}

		if ( $id !== null ) {
			$matched = $this->findById( $property, $id );
			if ( $matched === null ) {
				throw new InvalidArgumentException( "No select option matches id \"$id\"" );
			}
			return $matched->getId();
		}

		/** @var string $label */
		return $this->resolveByLabel( $property, $label );
	}

	private function findById( SelectProperty $property, string $id ): ?SelectOption {
		foreach ( $property->getOptions() as $option ) {
			if ( $option->getId() === $id ) {
				return $option;
			}
		}
		return null;
	}

}
