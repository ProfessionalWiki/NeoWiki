<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use LogicException;
use ProfessionalWiki\NeoWiki\Domain\Mapping\CurieExpander;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SchemaMapping;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfTerm;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use Psr\Log\LoggerInterface;

/**
 * Projects a {@see Page} into a target ontology using one page-level {@see Mapping} (OntologyMapping.md,
 * v1 near-1:1 term-substitution tier). The Mapping page's name is the projection target; it holds one
 * entry per mapped Schema. Per Subject whose Schema has an entry it emits `rdf:type <mapped class>`,
 * `rdfs:label` (always), and one triple per mapped property value — mapping only the vocabulary, because
 * the Subject IRI stays native (`neo-subj:`), the entity being the wiki's own. Unmapped properties are
 * absent, so the output is conformant to the target ontology. There is no intermediate-node synthesis and
 * no `neo:Relation` reification in v1; relation values become a direct triple to the target Subject's
 * native IRI, which may be untyped when that Subject has no entry of its own.
 *
 * Every quad is placed in the page's named graph for this projection's target (`{$base}/graph/{target}/page/{id}`,
 * #1053), so the per-page sync used by the native projection (NativeRdfProjection.md) works for an ontology
 * store too, and the native and ontology projections of a page can share one store without colliding. No
 * page-metadata triples are emitted.
 */
class OntologyMappingProjector implements PageProjector {

	private readonly string $target;
	private readonly CurieExpander $expander;

	public function __construct(
		private readonly Mapping $mapping,
		private readonly RdfNamespaces $namespaces,
		private readonly RdfValueMapperRegistry $valueMappers,
		private readonly LoggerInterface $logger,
	) {
		$this->target = $mapping->name->getText();
		$this->expander = new CurieExpander( $mapping->prefixes );
	}

	public function projectPage( Page $page ): QuadList {
		$graph = $this->namespaces->graph( $this->target, $page->getId() );
		$quads = [];

		foreach ( $page->getSubjects()->getAllSubjects()->asArray() as $subject ) {
			$schemaMapping = $this->mapping->forSchema( $subject->getSchemaName() );

			if ( $schemaMapping !== null ) {
				$quads = array_merge( $quads, $this->subjectQuads( $subject, $schemaMapping, $graph ) );
			}
		}

		return QuadList::fromArray( $quads );
	}

	/**
	 * Projects a single Subject on the page into the target ontology — its per-Subject block from
	 * {@see projectPage()} (mapped type, label, mapped property values, relations as direct triples)
	 * placed in the target's named graph. A Subject that is not on the page, or whose Schema has no
	 * entry in this Mapping, yields an empty list.
	 */
	public function projectSubject( Page $page, SubjectId $subjectId ): QuadList {
		$subject = $page->getSubjects()->getAllSubjects()->getSubject( $subjectId );

		if ( $subject === null ) {
			return new QuadList();
		}

		$schemaMapping = $this->mapping->forSchema( $subject->getSchemaName() );

		if ( $schemaMapping === null ) {
			return new QuadList();
		}

		return QuadList::fromArray(
			$this->subjectQuads( $subject, $schemaMapping, $this->namespaces->graph( $this->target, $page->getId() ) )
		);
	}

	/**
	 * @return Quad[]
	 */
	private function subjectQuads( Subject $subject, SchemaMapping $schemaMapping, Iri $graph ): array {
		$subjectIri = $this->namespaces->subject( $subject->id );

		$quads = [
			new Quad( $subjectIri, $this->namespaces->rdfsLabel(), RdfLiteralFactory::typed( $subject->label->text, 'string' ), $graph ),
		];

		$class = $this->expander->expand( $schemaMapping->subjectClass );
		if ( $class === null ) {
			// Cannot happen for a Mapping that passed save-time validation; guard the projection anyway.
			$this->logger->warning(
				'Mapping "' . $this->target . '" has an unresolvable subject class for Schema "'
				. $subject->getSchemaName()->getText() . '"; skipping the type triple.'
			);
		}
		else {
			array_unshift( $quads, new Quad( $subjectIri, $this->namespaces->rdfType(), $class, $graph ) );
		}

		foreach ( $subject->getStatements()->asArray() as $statement ) {
			$propertyMapping = $schemaMapping->properties->get( $statement->getPropertyName()->text );

			if ( $propertyMapping !== null ) {
				$quads = array_merge(
					$quads,
					$this->projectStatement( $statement, $propertyMapping, $subjectIri, $graph )
				);
			}
		}

		return $quads;
	}

	/**
	 * @return Quad[]
	 */
	private function projectStatement(
		Statement $statement,
		PropertyMapping $propertyMapping,
		Iri $subjectIri,
		Iri $graph
	): array {
		$predicate = $this->expander->expand( $propertyMapping->predicate );

		if ( $predicate === null ) {
			$this->logger->warning(
				'Mapping "' . $this->target . '" has an unresolvable predicate for property "'
				. $statement->getPropertyName()->text . '"; skipping it.'
			);
			return [];
		}

		if ( $statement->getPropertyType() === RelationType::NAME ) {
			return $this->projectRelationStatement( $statement, $predicate, $subjectIri, $graph );
		}

		return $this->projectValueStatement( $statement, $propertyMapping, $predicate, $subjectIri, $graph );
	}

	/**
	 * A relation value becomes a direct triple to each target Subject's native IRI. No `neo:Relation`
	 * node and no relation properties are projected: those are native-vocabulary constructs with no v1
	 * ontology mapping.
	 *
	 * @return Quad[]
	 */
	private function projectRelationStatement( Statement $statement, Iri $predicate, Iri $subjectIri, Iri $graph ): array {
		$value = $statement->getValue();

		if ( !$value instanceof RelationValue ) {
			return [];
		}

		$quads = [];

		foreach ( $value->relations as $relation ) {
			$quads[] = new Quad( $subjectIri, $predicate, $this->namespaces->subject( $relation->targetId ), $graph );
		}

		return $quads;
	}

	/**
	 * @return Quad[]
	 */
	private function projectValueStatement(
		Statement $statement,
		PropertyMapping $propertyMapping,
		Iri $predicate,
		Iri $subjectIri,
		Iri $graph
	): array {
		$terms = $this->valueMappers->mapValue( $statement->getPropertyType(), $statement->getValue() );

		if ( $terms === null ) {
			return [];
		}

		$quads = [];

		foreach ( $terms as $term ) {
			$object = $this->applyOverrides( $term, $propertyMapping, $statement->getPropertyName()->text );
			$quads[] = new Quad( $subjectIri, $predicate, $object, $graph );
		}

		return $quads;
	}

	/**
	 * Applies the optional datatype override or language tag to a projected value term. A url value
	 * projects as an {@see Iri} object; every other value type as a {@see Literal}. A datatype override is
	 * deliberate configuration and wins: it forces a literal with that datatype, even for a url value that
	 * would otherwise be an IRI object (and it wins over a language tag — an RDF literal cannot carry
	 * both; the validator rejects a Mapping that sets both). A language tag applies only to a plain string
	 * literal — it is ignored for a typed literal (number, date, …), whose datatype the writer's schema
	 * already fixed, and for an IRI object, which is not a literal at all.
	 *
	 * The language tag is re-validated here as well as at save time: a Mapping created outside the
	 * save-time validator (importDump, a page authored before validation existed) could carry a
	 * malformed tag, which would corrupt the serialized literal. An invalid tag is therefore dropped —
	 * the plain string literal is emitted and a warning logged — so a bad stored Mapping degrades the
	 * output instead of aborting the whole export.
	 */
	private function applyOverrides(
		RdfTerm $term,
		PropertyMapping $propertyMapping,
		string $propertyName
	): RdfTerm {
		if ( $propertyMapping->datatype !== null ) {
			$datatype = $this->expander->expand( $propertyMapping->datatype );

			return $datatype === null ? $term : new Literal( $this->lexicalForm( $term ), $datatype );
		}

		if ( $propertyMapping->language !== null && $term instanceof Literal && $this->isPlainString( $term ) ) {
			return $this->withLanguageTag( $term, $propertyMapping->language, $propertyName );
		}

		return $term;
	}

	/**
	 * The lexical form to reuse when a datatype override forces a value into a literal: a Literal's own
	 * lexical form, or an IRI object's IRI string (a url value being converted back to a literal).
	 */
	private function lexicalForm( RdfTerm $term ): string {
		if ( $term instanceof Literal ) {
			return $term->lexicalForm;
		}

		if ( $term instanceof Iri ) {
			return $term->value;
		}

		throw new LogicException( 'A projected value term is always an Iri or a Literal.' );
	}

	private function withLanguageTag( Literal $literal, string $language, string $propertyName ): Literal {
		if ( !Literal::isValidLanguageTag( $language ) ) {
			$this->logger->warning(
				'Mapping "' . $this->target . '" has an invalid language tag "' . $language
				. '" for property "' . $propertyName . '"; emitting the literal without a language tag.'
			);
			return $literal;
		}

		return new Literal( $literal->lexicalForm, $literal->datatype, $language );
	}

	private function isPlainString( Literal $literal ): bool {
		return $literal->datatype->value === RdfNamespaces::XSD . 'string';
	}

}
