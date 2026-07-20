<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * The IRI and prefix scheme for the native RDF projection (NativeRdfProjection.md § Namespaces).
 *
 * Every NeoWiki IRI lives under a per-wiki base URI so that sibling projections (native and
 * ontology-mapped) mint identical entity IRIs. Standard vocabulary (rdf, rdfs, xsd, dcterms) is
 * fixed and independent of the base.
 */
readonly class RdfNamespaces {

	public const string RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
	public const string RDFS = 'http://www.w3.org/2000/01/rdf-schema#';
	public const string XSD = 'http://www.w3.org/2001/XMLSchema#';
	public const string DCTERMS = 'http://purl.org/dc/terms/';

	// Local names of NeoWiki vocabulary terms under the `neo:` namespace.
	public const string CLASS_PAGE = 'Page';
	public const string CLASS_RELATION = 'Relation';
	public const string TERM_PAGE_NAME = 'pageName';
	public const string TERM_LAST_EDITOR = 'lastEditor';
	public const string TERM_CATEGORY = 'category';
	public const string TERM_MAIN_SUBJECT = 'mainSubject';
	public const string TERM_HAS_SUBJECT = 'hasSubject';
	public const string TERM_SOURCE = 'source';
	public const string TERM_TARGET = 'target';
	public const string TERM_RELATION_TYPE = 'relationType';

	public string $baseUri;

	public function __construct( string $baseUri ) {
		$this->baseUri = rtrim( $baseUri, '/' );
	}

	public function subject( SubjectId $id ): Iri {
		return new Iri( $this->subjectIriBase() . $id->text );
	}

	/**
	 * The IRI prefix a Subject id extends into its concept URI ({@see subject()}) — `$base/entity/`,
	 * the `neo-subj:` namespace. Exposed so surfaces that build a Subject IRI without a SubjectId in
	 * hand (the Data tab's copy-IRI control, seeded via a JS config var) share this one rule rather
	 * than re-deriving the `/entity/` path and the base-URI trimming.
	 */
	public function subjectIriBase(): string {
		return $this->baseUri . '/entity/';
	}

	public function property( string $propertyName ): Iri {
		return new Iri( $this->baseUri . '/prop/' . self::localName( $propertyName ) );
	}

	public function schemaClass( SchemaName $schemaName ): Iri {
		return new Iri( $this->baseUri . '/schema/' . self::localName( $schemaName->getText() ) );
	}

	public function relationNode( RelationId $id ): Iri {
		return new Iri( $this->baseUri . '/relation/' . $id->asString() );
	}

	public function page( PageId $id ): Iri {
		return new Iri( $this->baseUri . '/page/' . $id->id );
	}

	/**
	 * The named-graph IRI for a page under a given projection (#1053). Qualifying the graph by
	 * projection lets several projections of the same page (native, an ontology target, …) live in one
	 * triple store without the per-page replace sync of one wiping another's triples. The page id stays
	 * in the IRI so per-page provenance and that sync are unchanged. This is distinct from {@see page()},
	 * the projection-independent page *resource* IRI that keeps appearing inside the triples.
	 *
	 * The projection name is an author-controlled string (a Mapping target, or the native `native`), so
	 * it runs through the same {@see localName()} encoding as Property and Schema names.
	 */
	public function graph( string $projection, PageId $id ): Iri {
		return new Iri( $this->baseUri . '/graph/' . self::localName( $projection ) . '/page/' . $id->id );
	}

	public function term( string $localName ): Iri {
		return new Iri( $this->baseUri . '/ontology/' . $localName );
	}

	public function rdfType(): Iri {
		return new Iri( self::RDF . 'type' );
	}

	public function rdfsLabel(): Iri {
		return new Iri( self::RDFS . 'label' );
	}

	public function dctermsCreated(): Iri {
		return new Iri( self::DCTERMS . 'created' );
	}

	public function dctermsModified(): Iri {
		return new Iri( self::DCTERMS . 'modified' );
	}

	public function xsd( string $localName ): Iri {
		return new Iri( self::XSD . $localName );
	}

	/**
	 * Turns a user-authored name (a Property, Schema or Relation-Type name, or a Relation-property
	 * key) into the local part of its IRI. Every string that enters an IRI here is untrusted, so the
	 * encoding is a security boundary, not just cosmetics: without it a name like `Rev>2020` would
	 * close the IRIREF early and a crafted name could forge extra triples.
	 *
	 * The rule (NativeRdfProjection.md Q7):
	 * 1. Spaces become underscores, keeping the spec's readable `neo-prop:Has_author` convention.
	 * 2. `%` and the IRIREF-illegal ASCII characters (`< > " { } | ^ \` and backtick) plus control
	 *    characters (0x00–0x1F, 0x7F) are percent-encoded, so a name can never break out of the IRI.
	 * 3. Everything else, including non-ASCII Unicode, is kept raw so multilingual names stay readable.
	 *
	 * The base URI is trusted admin config and is not encoded here.
	 *
	 * CAVEAT: step 1 collides when a name already contains an underscore. "Has author" and "Has_author"
	 * both map to the local name `Has_author` and therefore share a predicate IRI. The native
	 * projection accepts this; disambiguation would require another scheme.
	 */
	public static function localName( string $name ): string {
		$encoded = '';

		foreach ( str_split( str_replace( ' ', '_', $name ) ) as $byte ) {
			$encoded .= self::isIriLocalByte( $byte ) ? $byte : sprintf( '%%%02X', ord( $byte ) );
		}

		return $encoded;
	}

	private static function isIriLocalByte( string $byte ): bool {
		$code = ord( $byte );

		// Control characters (0x00–0x1F and DEL) are never allowed in an IRIREF. Bytes >= 0x80 are
		// part of a raw UTF-8 sequence and pass through, keeping Unicode readable.
		if ( $code <= 0x1F || $code === 0x7F ) {
			return false;
		}

		return !in_array( $byte, [ '%', '<', '>', '"', '{', '}', '|', '^', '\\', '`' ], true );
	}

	/**
	 * The namespaces a serializer may abbreviate. Projection-independent: the graph namespace is left out
	 * because a graph's local name is a page id, and a prefixed name cannot start with a digit, so no
	 * graph IRI this mints is abbreviable anyway.
	 *
	 * @return array<string, string> Prefix label to namespace IRI, for serializer abbreviation.
	 */
	public function prefixMap(): array {
		return [
			'neo' => $this->baseUri . '/ontology/',
			'neo-subj' => $this->subjectIriBase(),
			'neo-prop' => $this->baseUri . '/prop/',
			'neo-schema' => $this->baseUri . '/schema/',
			'neo-rel' => $this->baseUri . '/relation/',
			'neo-page' => $this->baseUri . '/page/',
			'rdf' => self::RDF,
			'rdfs' => self::RDFS,
			'xsd' => self::XSD,
			'dcterms' => self::DCTERMS,
		];
	}

}
