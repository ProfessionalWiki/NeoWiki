<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

/**
 * Parses serialized Schema references at persistence boundaries. A reference that explicitly names
 * the local source canonicalizes to the bare local form, so one Schema is never referenced under two
 * identities. Mirrors {@see \ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser}; source keys
 * compare byte-for-byte (ADR 27).
 */
class SchemaReferenceParser {

	public function __construct(
		private readonly string $localSourceKey
	) {
	}

	/**
	 * @param string|array<string, mixed> $value
	 *
	 * @throws \InvalidArgumentException
	 */
	public function parse( string|array $value ): SchemaReference {
		$reference = SchemaReference::fromSerializedValue( $value );

		if ( $reference->getSource() === $this->localSourceKey ) {
			return SchemaReference::local( $reference->getName() );
		}

		return $reference;
	}

}
