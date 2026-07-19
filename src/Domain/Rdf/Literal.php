<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

use InvalidArgumentException;

/**
 * An RDF literal: a lexical form plus a datatype IRI, and optionally a language tag.
 *
 * The native projection only mints datatyped literals (the language tag stays null), but the
 * model carries the tag so language-tagged literals remain expressible for future projections.
 *
 * The language tag is a domain invariant: a non-null tag must be BCP-47-shaped, so it cannot smuggle
 * a `"`, a datatype, or other syntax into the serialized `"lexical"@tag` form. Construction sites are
 * controlled (the native projection never sets a tag; the ontology projector validates before
 * constructing), so an invalid tag is a programming error and throws.
 */
readonly class Literal implements RdfTerm {

	// A BCP-47-shaped language tag: hyphen-separated subtags of 1–8 characters, the primary subtag
	// alphabetic and the rest alphanumeric. Kept in sync with the `lang` pattern in
	// mappingContentSchema.json (the save-time layer). The `D` modifier pins `$` to the very end of the
	// string so a trailing newline cannot slip characters past the check.
	private const string LANGUAGE_TAG_PATTERN = '/^[A-Za-z]{1,8}(-[A-Za-z0-9]{1,8})*$/D';

	public function __construct(
		public string $lexicalForm,
		public Iri $datatype,
		public ?string $languageTag = null,
	) {
		if ( $languageTag !== null && !self::isValidLanguageTag( $languageTag ) ) {
			throw new InvalidArgumentException( 'Invalid RDF language tag: "' . $languageTag . '".' );
		}
	}

	public static function isValidLanguageTag( string $tag ): bool {
		return preg_match( self::LANGUAGE_TAG_PATTERN, $tag ) === 1;
	}

	public function isLanguageTagged(): bool {
		return $this->languageTag !== null;
	}

	public function equals( RdfTerm $other ): bool {
		return $other instanceof self
			&& $this->lexicalForm === $other->lexicalForm
			&& $this->datatype->equals( $other->datatype )
			&& $this->languageTag === $other->languageTag;
	}

}
