<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;

/**
 * Turns a written Schema reference — a `schema` or `targetSchema` field, a name from a form — into a
 * {@see SchemaReference} that names one Schema however it was written down.
 *
 * Two rules make it one: a reference naming this wiki's own Source becomes a local one (ADR 23), and a
 * local name becomes the name of the Schema's page (ADR 17).
 *
 * Every reference crossing a boundary — a revision slot, Schema JSON, a REST body, a Lua call — is
 * parsed here rather than constructed directly, as
 * {@see \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser} is for Subject ids.
 */
readonly class SchemaReferenceParser {

	public function __construct(
		private string $localSourceKey,
		private SchemaReferenceNormalizer $normalizer,
	) {
	}

	/**
	 * @param mixed $json The value of a `schema` or `targetSchema` field.
	 *
	 * @throws InvalidArgumentException When $json is not a well-formed reference.
	 */
	public function fromJson( mixed $json ): SchemaReference {
		return $this->normalizer->normalize( SchemaReference::fromJson( $json, $this->localSourceKey ) );
	}

	/**
	 * A Schema of this wiki, named by a caller who can mean no other Source.
	 *
	 * @throws InvalidArgumentException When $name is no Schema name.
	 */
	public function localName( string $name ): SchemaReference {
		return $this->normalizer->normalize( SchemaReference::local( new SchemaName( $name ) ) );
	}

}
