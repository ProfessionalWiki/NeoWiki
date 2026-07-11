<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

/**
 * An RDF literal: a lexical form plus a datatype IRI, and optionally a language tag.
 *
 * The native projection only mints datatyped literals (the language tag stays null), but the
 * model carries the tag so language-tagged literals remain expressible for future projections.
 */
readonly class Literal implements RdfTerm {

	public function __construct(
		public string $lexicalForm,
		public Iri $datatype,
		public ?string $languageTag = null,
	) {
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
