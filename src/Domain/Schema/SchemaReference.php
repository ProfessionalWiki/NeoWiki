<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use Stringable;

/**
 * Which Schema, and where it comes from (ADR 23). Schema identity stays the name (ADR 17); this pairs
 * that name with the Source offering it, which need not be the Source of the Subject using it.
 *
 * A Schema of this wiki is referenced by its bare name, which is its page title in the Schema namespace.
 * That title may itself contain a colon (`ISO:9001`), so a stored string is never split: it is always
 * one local name, exactly as it was before Sources existed. A Schema from elsewhere is stored as an
 * object instead, `{"source": "<key>", "name": "<name>"}`, which no local name can be mistaken for.
 *
 * {@see getText()} renders a reference for people. It is one-way: nothing reads it back.
 */
readonly class SchemaReference implements Stringable {

	/**
	 * @param ?string $source Source key, or null for a Schema of this wiki.
	 */
	private function __construct(
		public ?string $source,
		public SchemaName $name,
	) {
	}

	public static function local( SchemaName $name ): self {
		return new self( null, $name );
	}

	/**
	 * @throws InvalidArgumentException When $source is not a well-formed Source key.
	 */
	public static function sourced( string $source, SchemaName $name ): self {
		if ( !SubjectId::isValidSourceKey( $source ) ) {
			throw new InvalidArgumentException( "Source key has the wrong format: '$source'" );
		}

		return new self( $source, $name );
	}

	/**
	 * Reads a stored reference. A reference naming this wiki's own Source canonicalizes to a local one,
	 * so a Schema has one identity however it was written down — the same rule Subject ids follow, and
	 * what keeps {@see equals()} agreeing with what the resolver hands back.
	 *
	 * @param mixed $json The value of a `schema` or `targetSchema` field.
	 *
	 * @throws InvalidArgumentException When $json is not a well-formed reference.
	 */
	public static function fromJson( mixed $json, string $localSourceKey ): self {
		if ( is_string( $json ) ) {
			return self::local( new SchemaName( $json ) );
		}

		if ( !is_array( $json ) || !is_string( $json['source'] ?? null ) || !is_string( $json['name'] ?? null ) ) {
			throw new InvalidArgumentException( 'Schema reference must be a name or a {source, name} object' );
		}

		if ( $json['source'] === $localSourceKey ) {
			return self::local( new SchemaName( $json['name'] ) );
		}

		return self::sourced( $json['source'], new SchemaName( $json['name'] ) );
	}

	/**
	 * @return string|array{source: string, name: string} A local reference keeps the bare string every
	 *   stored reference has always used; only a sourced one costs the object form.
	 */
	public function toJson(): string|array {
		if ( $this->source === null ) {
			return $this->name->getText();
		}

		return [ 'source' => $this->source, 'name' => $this->name->getText() ];
	}

	public function isLocal(): bool {
		return $this->source === null;
	}

	/**
	 * How a reference reads to a person, in a message or a UI. Not a serialization: `sourceKey:Name` is
	 * ambiguous with a colon-bearing local name, which is why nothing parses it.
	 */
	public function getText(): string {
		return $this->source === null ? $this->name->getText() : $this->source . ':' . $this->name->getText();
	}

	public function equals( self $other ): bool {
		return $this->source === $other->source && $this->name->getText() === $other->name->getText();
	}

	public function __toString(): string {
		return $this->getText();
	}

}
