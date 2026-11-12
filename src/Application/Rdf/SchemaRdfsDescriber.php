<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Domain\LanguageTag;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\BooleanType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\MonolingualTextType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\NumberType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfTerm;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;

/**
 * Describes Schemas in RDF Schema, in the vocabulary the native projection uses: each Schema as a class, and
 * each of its Property Definitions as a property with the Schema as domain.
 *
 * The vocabulary IRIs are built once and shared by every quad, which keeps a whole wiki's description small.
 */
class SchemaRdfsDescriber {

	private readonly Iri $graph;
	private readonly Iri $type;
	private readonly Iri $label;
	private readonly Iri $comment;
	private readonly Iri $domain;
	private readonly Iri $range;
	private readonly Iri $class;
	private readonly Iri $property;
	private readonly Iri $string;

	public function __construct(
		private readonly RdfNamespaces $namespaces,
	) {
		$this->graph = $namespaces->schemaGraph();
		$this->type = $namespaces->rdfType();
		$this->label = $namespaces->rdfsLabel();
		$this->comment = $namespaces->rdfs( 'comment' );
		$this->domain = $namespaces->rdfs( 'domain' );
		$this->range = $namespaces->rdfs( 'range' );
		$this->class = $namespaces->rdfs( 'Class' );
		$this->property = $namespaces->rdf( 'Property' );
		$this->string = $namespaces->xsd( 'string' );
	}

	public function describe( Schema ...$schemas ): QuadList {
		return new QuadList( ...array_merge( ...array_map( $this->schemaQuads( ... ), $schemas ) ) );
	}

	/**
	 * @return Quad[]
	 */
	private function schemaQuads( Schema $schema ): array {
		$class = $this->namespaces->schemaClass( $schema->getName() );

		$quads = [
			$this->quad( $class, $this->type, $this->class ),
			$this->quad( $class, $this->label, new Literal( $schema->getName()->getText(), $this->string ) ),
			...$this->commentQuads( $class, $schema->getDescription() ),
		];

		foreach ( $schema->getAllProperties()->asMap() as $name => $property ) {
			array_push( $quads, ...$this->propertyQuads( $schema, $class, (string)$name, $property ) );
		}

		return $quads;
	}

	/**
	 * @return Quad[]
	 */
	private function propertyQuads( Schema $schema, Iri $class, string $name, PropertyDefinition $property ): array {
		$iri = $this->namespaces->property( $schema->getName(), $name );

		$quads = [
			$this->quad( $iri, $this->type, $this->property ),
			...array_map( fn ( Literal $label ): Quad => $this->quad( $iri, $this->label, $label ), $this->labels( $name, $property ) ),
			...$this->commentQuads( $iri, $property->getDescription() ),
			$this->quad( $iri, $this->domain, $class ),
		];

		$range = $this->rangeOf( $property );

		if ( $range !== null ) {
			$quads[] = $this->quad( $iri, $this->range, $range );
		}

		return $quads;
	}

	/**
	 * @return Literal[]
	 */
	private function labels( string $name, PropertyDefinition $property ): array {
		$labels = [];

		foreach ( $property->getLabels() as $language => $text ) {
			// Save-time validation can be bypassed by an import, and a Literal refuses an invalid tag.
			if ( LanguageTag::isValid( (string)$language ) ) {
				$labels[] = RdfLiteralFactory::languageTagged( $text, (string)$language );
			}
		}

		return $labels === [] ? [ new Literal( $name, $this->string ) ] : $labels;
	}

	/**
	 * @return Quad[]
	 */
	private function commentQuads( Iri $resource, string $description ): array {
		if ( $description === '' ) {
			return [];
		}

		return [ $this->quad( $resource, $this->comment, new Literal( $description, $this->string ) ) ];
	}

	/**
	 * What the native projection emits for the property's values. An extension's Property Type gets no range,
	 * since its RDF value mapper does not declare a datatype.
	 */
	private function rangeOf( PropertyDefinition $property ): ?Iri {
		if ( $property instanceof RelationProperty ) {
			return $this->relationRange( $property );
		}

		return match ( $property->getPropertyType() ) {
			TextType::NAME, SelectType::NAME => $this->string,
			// Whole numbers project as xsd:integer, which XSD derives from xsd:decimal.
			NumberType::NAME => $this->namespaces->xsd( 'decimal' ),
			BooleanType::NAME => $this->namespaces->xsd( 'boolean' ),
			DateType::NAME => $this->namespaces->xsd( 'date' ),
			DateTimeType::NAME => $this->namespaces->xsd( 'dateTime' ),
			MonolingualTextType::NAME => $this->namespaces->rdf( 'langString' ),
			// A url value projects as an IRI or, when it is not an absolute IRI, as an xsd:anyURI literal.
			default => null,
		};
	}

	/**
	 * Several ranges on one property intersect, so a relation to several Schemas gets none. A Schema of
	 * another Source has no class IRI of its own yet.
	 */
	private function relationRange( RelationProperty $property ): ?Iri {
		$targets = $property->getTargetSchemas();

		if ( count( $targets ) !== 1 || !$targets[0]->isLocal() ) {
			return null;
		}

		return $this->namespaces->schemaClass( $targets[0]->name );
	}

	private function quad( Iri $subject, Iri $predicate, RdfTerm $object ): Quad {
		return new Quad( $subject, $predicate, $object, $this->graph );
	}

}
