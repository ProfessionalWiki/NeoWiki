<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;

class SelectOption {

	public function __construct(
		private readonly string $id,
		private readonly string $label,
	) {
		if ( $id === '' ) {
			throw new InvalidArgumentException( 'Select option id must not be empty' );
		}

		if ( trim( $label ) === '' ) {
			throw new InvalidArgumentException( 'Select option label must not be empty' );
		}
	}

	public function getId(): string {
		return $this->id;
	}

	public function getLabel(): string {
		return $this->label;
	}

	public function normalizedLabel(): string {
		return strtolower( trim( $this->label ) );
	}

	public function equals( self $other ): bool {
		return $this->id === $other->id
			&& $this->label === $other->label;
	}

	/**
	 * @return array{id: string, label: string}
	 */
	public function toJson(): array {
		return [
			'id' => $this->id,
			'label' => $this->label,
		];
	}

	public static function fromJson( mixed $json ): self {
		if ( !is_array( $json ) ) {
			throw new InvalidArgumentException( 'Select option must be an object' );
		}

		if ( !isset( $json['id'] ) || !is_string( $json['id'] ) ) {
			throw new InvalidArgumentException( 'Select option missing string id' );
		}

		if ( !isset( $json['label'] ) || !is_string( $json['label'] ) ) {
			throw new InvalidArgumentException( 'Select option missing string label' );
		}

		return new self( $json['id'], $json['label'] );
	}

}
