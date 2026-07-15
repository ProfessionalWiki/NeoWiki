<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;

/**
 * A reference to a Schema, resolved through its Source (ADR 23, "Schemas come from Sources"). The
 * source is independent of any Subject's source. A null source means the local wiki; the SchemaName
 * stays intact underneath, since locally a Schema name is a page title in the Schema namespace
 * (ADR 17) and a source-qualified reference is not a valid title.
 *
 * The serialized form is a bare name string for a local reference and a `{ source, name }` object
 * for a foreign one. Names are page titles and may legitimately contain colons, so a
 * string-concatenation grammar would be ambiguous; the object form keeps source and name separate.
 */
readonly class SchemaReference {

	public function __construct(
		private ?string $source,
		private SchemaName $name,
	) {
	}

	public static function local( SchemaName $name ): self {
		return new self( null, $name );
	}

	/**
	 * @param string|array<string, mixed> $value A bare name (local) or a `{ source, name }` object (foreign).
	 *
	 * @throws InvalidArgumentException
	 */
	public static function fromSerializedValue( string|array $value ): self {
		if ( is_string( $value ) ) {
			return self::local( new SchemaName( $value ) );
		}

		if ( !is_string( $value['source'] ?? null ) || !is_string( $value['name'] ?? null ) ) {
			throw new InvalidArgumentException( 'A foreign Schema reference needs a string source and name.' );
		}

		return new self( $value['source'], new SchemaName( $value['name'] ) );
	}

	public function getSource(): ?string {
		return $this->source;
	}

	public function getName(): SchemaName {
		return $this->name;
	}

	/**
	 * @return string|array{source: string, name: string} A bare name for a local reference, a
	 *  `{ source, name }` object for a foreign one.
	 */
	public function toSerializedValue(): string|array {
		if ( $this->source === null ) {
			return $this->name->getText();
		}

		return [ 'source' => $this->source, 'name' => $this->name->getText() ];
	}

	public function equals( self $other ): bool {
		return $this->source === $other->source
			&& $this->name->getText() === $other->name->getText();
	}

}
