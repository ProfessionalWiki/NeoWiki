<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;

/**
 * Turns a written Schema reference — a `schema` or `targetSchema` field, a name from a form — into a
 * {@see SchemaReference} that names one Schema however it was written down.
 *
 * Two rules make it one: a reference naming this wiki's own Source becomes a local one (ADR 23), and a
 * local name becomes the name of the Schema's page (ADR 17). Both were applied in different places
 * before, so a reference could satisfy one and not the other, and {@see SchemaReference::equals()} then
 * disagreed with what the resolver handed back.
 *
 * Every reference crossing a boundary — a revision slot, Schema JSON, a REST body, a Lua call — is
 * parsed here rather than constructed directly. The same rule {@see \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser}
 * follows for Subject ids, for the same reason.
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
	 * A Schema of this wiki, named by a caller who cannot mean any other — a Subject is only ever
	 * created in the local Source, so the Schema it names is a local one too.
	 *
	 * @throws InvalidArgumentException When $name is no Schema name.
	 */
	public function localName( string $name ): SchemaReference {
		return $this->normalizer->normalize( SchemaReference::local( new SchemaName( $name ) ) );
	}

}
