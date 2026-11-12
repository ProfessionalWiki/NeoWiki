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

	// The ASCII characters of RFC 3987 ipchar: unreserved and sub-delims characters, ":" and "@".
	private const string ASCII_IPCHARS = '/^[A-Za-z0-9\-._~!$&\'()*+,;=:@]*$/D';

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

	/**
	 * Scoped to the Schema, as Property Definitions are: "Name" in Person and "Name" in City are two predicates.
	 */
	public function property( SchemaName $schemaName, string $propertyName ): Iri {
		return new Iri( $this->propertyNamespace( self::localName( $schemaName->getText() ) ) . self::localName( $propertyName ) );
	}

	private function propertyNamespace( string $schemaLocalName ): string {
		return $this->propertyIriBase() . $schemaLocalName . '/';
	}

	private function propertyIriBase(): string {
		return $this->baseUri . '/prop/';
	}

	/**
	 * One prefix per Schema whose properties the quads use as predicates or describe as subjects, labelled with
	 * the Schema's name as its predicates spell it. A url value that points at a property IRI does not use it, so
	 * objects are left out.
	 *
	 * @return array<array-key, string> Prefix label to namespace IRI.
	 */
	public function propertyPrefixes( QuadList $quads ): array {
		$propertyIriBase = $this->propertyIriBase();
		$prefixes = [];

		foreach ( $quads->asArray() as $quad ) {
			foreach ( [ $quad->subject, $quad->predicate ] as $iri ) {
				$schemaLocalName = self::schemaLocalNameOfProperty( $iri, $propertyIriBase );

				if ( $schemaLocalName !== null ) {
					$prefixes[$schemaLocalName] = $this->propertyNamespace( $schemaLocalName );
				}
			}
		}

		return $prefixes;
	}

	private static function schemaLocalNameOfProperty( Iri $iri, string $propertyIriBase ): ?string {
		if ( !str_starts_with( $iri->value, $propertyIriBase ) ) {
			return null;
		}

		$localNames = explode( '/', substr( $iri->value, strlen( $propertyIriBase ) ) );

		return count( $localNames ) === 2 ? $localNames[0] : null;
	}

	public function schemaClass( SchemaName $schemaName ): Iri {
		return new Iri( $this->baseUri . '/schema/' . self::localName( $schemaName->getText() ) );
	}

	public function relationNode( RelationId $id ): Iri {
		return new Iri( $this->baseUri . '/relation/' . $id->asString() );
	}

	/**
	 * A synthesized intermediate node of an ontology projection, anchored on the Subject it expands
	 * (OntologyMapping.md § Synthesized-node IRIs). For a subject-scoped node the keys are its chain of
	 * Mapping node keys from the Subject down, so nesting reads off the IRI. For a value-scoped node they
	 * are its own key and the value's position — its parent chain is left out.
	 *
	 * These IRIs are deliberately not in {@see prefixMap()}: like a graph IRI, their local part can start
	 * with a digit, so a prefix would not abbreviate them anyway.
	 */
	public function synthesizedNode( SubjectId $subjectId, string ...$keys ): Iri {
		return new Iri(
			$this->baseUri . '/node/' . $subjectId->text . '/'
				. implode( '/', array_map( self::localName( ... ), $keys ) )
		);
	}

	/**
	 * A synthesized intermediate node anchored on the Relation whose value it mediates. Relations carry a
	 * persistent ID ([ADR 10](../../../docs/adr/010-add-guids-to-relations.md)), so the node for a given
	 * relation value is the same node in every projection and across re-projections.
	 */
	public function synthesizedRelationNode( RelationId $relationId ): Iri {
		return new Iri( $this->baseUri . '/node/' . $relationId->asString() );
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

	/**
	 * The graph of the Schemas' RDFS description. No page graph can collide with it, since every IRI that
	 * {@see graph()} mints ends in `/page/{id}`.
	 */
	public function schemaGraph(): Iri {
		return new Iri( $this->baseUri . '/graph/schemas' );
	}

	/**
	 * A named graph reserved for probing whether a store can be reached, so an update naming it is
	 * harmless whatever the store holds: nothing writes to it. No page graph can collide with it,
	 * whatever a projection is named, since every IRI that {@see graph()} mints ends in `/page/{id}`.
	 */
	public function probeGraph(): Iri {
		return new Iri( $this->baseUri . '/graph/liveness-probe' );
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

	public function rdf( string $localName ): Iri {
		return new Iri( self::RDF . $localName );
	}

	public function rdfs( string $localName ): Iri {
		return new Iri( self::RDFS . $localName );
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
	 * Turns a user-authored name (a Property or Schema name) into the local part of its IRI. Every
	 * string that enters an IRI here is untrusted, so the encoding is a security boundary, not just
	 * cosmetics: without it a name like `Rev>2020` would close the IRIREF early and a crafted name could
	 * forge extra triples.
	 *
	 * The rule (NativeRdfProjection.md Q7):
	 * 1. Spaces become underscores, keeping IRIs such as `Person:Birth_date` readable.
	 * 2. Every character outside RFC 3987 `ipchar` is percent-encoded: `%`, the IRIREF-illegal and control
	 *    characters, so a name can never break out of the IRI; `/`, `#`, `?`, `[` and `]`, so it can neither add a
	 *    path segment, as in `$base/prop/{Schema}/{Property}`, nor start a fragment or query; and the non-ASCII
	 *    characters an IRI cannot hold, such as C1 controls and private-use characters.
	 * 3. Other non-ASCII characters are kept raw, so multilingual names stay readable.
	 *
	 * The base URI is trusted admin config and is not encoded here.
	 *
	 * CAVEAT: step 1 collides when a name already contains an underscore. "Has author" and "Has_author"
	 * both map to the local name `Has_author` and therefore share a predicate IRI. The native
	 * projection accepts this; disambiguation would require another scheme.
	 */
	public static function localName( string $name ): string {
		$underscored = str_replace( ' ', '_', $name );

		if ( preg_match( self::ASCII_IPCHARS, $underscored ) === 1 ) {
			return $underscored;
		}

		$encoded = '';

		foreach ( mb_str_split( $underscored, 1, 'UTF-8' ) as $character ) {
			$encoded .= self::isIpchar( $character ) ? $character : rawurlencode( $character );
		}

		return $encoded;
	}

	/**
	 * The ucschar ranges are compared as numbers: PCRE builds disagree on a character class this large.
	 */
	private static function isIpchar( string $character ): bool {
		if ( strlen( $character ) === 1 ) {
			return preg_match( self::ASCII_IPCHARS, $character ) === 1;
		}

		$codePoint = mb_ord( $character, 'UTF-8' );

		return $codePoint !== false && self::isUcsChar( $codePoint );
	}

	private static function isUcsChar( int $codePoint ): bool {
		return ( $codePoint >= 0xA0 && $codePoint <= 0xD7FF )
			|| ( $codePoint >= 0xF900 && $codePoint <= 0xFDCF )
			|| ( $codePoint >= 0xFDF0 && $codePoint <= 0xFFEF )
			|| ( $codePoint >= 0x10000 && $codePoint <= 0xEFFFD
				&& ( $codePoint & 0xFFFF ) <= 0xFFFD
				&& !( $codePoint >= 0xE0000 && $codePoint <= 0xE0FFF ) );
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
