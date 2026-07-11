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
		return new Iri( $this->baseUri . '/entity/' . $id->text );
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
	 * Turns a Property or Schema name into the local part of its IRI by substituting spaces with
	 * underscores (NativeRdfProjection.md Q7).
	 *
	 * CAVEAT: this collides when a name already contains an underscore. "Has author" and "Has_author"
	 * both map to the local name `Has_author` and therefore share a predicate IRI. The native
	 * projection accepts this; disambiguation would require percent-encoding or another scheme.
	 */
	public static function localName( string $name ): string {
		return str_replace( ' ', '_', $name );
	}

	/**
	 * @return array<string, string> Prefix label to namespace IRI, for serializer abbreviation.
	 */
	public function prefixMap(): array {
		return [
			'neo' => $this->baseUri . '/ontology/',
			'neo-subj' => $this->baseUri . '/entity/',
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
