<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\LanguageTag;

/**
 * An RDF literal: a lexical form plus a datatype IRI, and optionally a language tag.
 *
 * The language tag is a domain invariant: a non-null tag must be BCP-47-shaped, so it cannot smuggle
 * a `"`, a datatype, or other syntax into the serialized `"lexical"@tag` form. Construction sites are
 * controlled (the native projection tags only monolingual text values, whose parts validate their own
 * tag; the ontology projector validates before constructing), so an invalid tag is a programming error
 * and throws.
 */
readonly class Literal implements RdfTerm {

	public function __construct(
		public string $lexicalForm,
		public Iri $datatype,
		public ?string $languageTag = null,
	) {
		if ( $languageTag !== null && !LanguageTag::isValid( $languageTag ) ) {
			throw new InvalidArgumentException( 'Invalid RDF language tag: "' . $languageTag . '".' );
		}
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
