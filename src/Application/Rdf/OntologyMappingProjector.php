<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Mapping\CurieExpander;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Literal;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use Psr\Log\LoggerInterface;

/**
 * Projects a {@see Page} into a target ontology using the {@see Mapping}s configured for that target
 * (OntologyMapping.md, v1 near-1:1 term-substitution tier). Per Subject that has a Mapping for the
 * target it emits `rdf:type <mapped class>`, `rdfs:label` (always), and one triple per mapped property
 * value — mapping only the vocabulary; the Subject IRI stays native (`neo-subj:`), because the entity
 * is the wiki's own. Unmapped properties are absent, so the output is conformant to the target
 * ontology. There is no intermediate-node synthesis and no `neo:Relation` reification in v1; relation
 * values become a direct triple to the target Subject's native IRI, which may be untyped when that
 * Subject has no Mapping of its own.
 *
 * Every quad is placed in the page's named graph for this projection's target (`{$base}/graph/{target}/page/{id}`,
 * #1053), so the per-page sync used by the native projection (NativeRdfProjection.md) works for an ontology
 * store too, and the native and ontology projections of a page can share one store without colliding. No
 * page-metadata triples are emitted.
 */
class OntologyMappingProjector implements PageProjector {

	/**
	 * @var array<string, Mapping> Chosen Mapping per Schema name, for the projector's target.
	 */
	private readonly array $mappingsBySchema;

	/**
	 * @param Mapping[] $mappings The Mappings for the store's target (one per Schema after tie-breaking).
	 */
	public function __construct(
		private readonly string $target,
		array $mappings,
		private readonly RdfNamespaces $namespaces,
		private readonly RdfValueMapperRegistry $valueMappers,
		private readonly LoggerInterface $logger,
	) {
		$this->mappingsBySchema = $this->resolveMappingsBySchema( $mappings );
	}

	/**
	 * @param Mapping[] $mappings
	 * @return array<string, Mapping>
	 */
	private function resolveMappingsBySchema( array $mappings ): array {
		$grouped = [];

		foreach ( $mappings as $mapping ) {
			$grouped[$mapping->schema->getText()][] = $mapping;
		}

		$resolved = [];

		foreach ( $grouped as $schemaName => $candidates ) {
			$resolved[$schemaName] = $this->pickMapping( $schemaName, $candidates );
		}

		return $resolved;
	}

	/**
	 * The v1 guarantee is one Mapping per (Schema, target), enforced at save time. Should duplicate
	 * pages exist anyway (pre-production race), the projection stays deterministic: pick the
	 * alphabetically first page title and log, rather than depend on enumeration order.
	 *
	 * @param Mapping[] $candidates
	 */
	private function pickMapping( string $schemaName, array $candidates ): Mapping {
		if ( count( $candidates ) === 1 ) {
			return $candidates[0];
		}

		usort(
			$candidates,
			static fn ( Mapping $a, Mapping $b ): int => strcmp( $a->name->getText(), $b->name->getText() )
		);

		$this->logger->warning(
			'Multiple Mappings claim Schema "' . $schemaName . '" for target "' . $this->target
			. '"; using "' . $candidates[0]->name->getText() . '".'
		);

		return $candidates[0];
	}

	public function projectPage( Page $page ): QuadList {
		$graph = $this->namespaces->graph( $this->target, $page->getId() );
		$quads = [];

		foreach ( $page->getSubjects()->getAllSubjects()->asArray() as $subject ) {
			$mapping = $this->mappingsBySchema[$subject->getSchemaName()->getText()] ?? null;

			if ( $mapping !== null ) {
				$quads = array_merge( $quads, $this->projectSubject( $subject, $mapping, $graph ) );
			}
		}

		return QuadList::fromArray( $quads );
	}

	/**
	 * @return Quad[]
	 */
	private function projectSubject( Subject $subject, Mapping $mapping, Iri $graph ): array {
		$expander = new CurieExpander( $mapping->prefixes );
		$subjectIri = $this->namespaces->subject( $subject->id );

		$quads = [
			new Quad( $subjectIri, $this->namespaces->rdfsLabel(), RdfLiteralFactory::typed( $subject->label->text, 'string' ), $graph ),
		];

		$class = $expander->expand( $mapping->subjectClass );
		if ( $class === null ) {
			// Cannot happen for a Mapping that passed save-time validation; guard the projection anyway.
			$this->logger->warning( 'Mapping "' . $mapping->name->getText() . '" has an unresolvable subject class; skipping the type triple.' );
		}
		else {
			array_unshift( $quads, new Quad( $subjectIri, $this->namespaces->rdfType(), $class, $graph ) );
		}

		foreach ( $subject->getStatements()->asArray() as $statement ) {
			$propertyMapping = $mapping->properties->get( $statement->getPropertyName()->text );

			if ( $propertyMapping !== null ) {
				$quads = array_merge(
					$quads,
					$this->projectStatement( $statement, $propertyMapping, $expander, $subjectIri, $graph, $mapping )
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
		CurieExpander $expander,
		Iri $subjectIri,
		Iri $graph,
		Mapping $mapping
	): array {
		$predicate = $expander->expand( $propertyMapping->predicate );

		if ( $predicate === null ) {
			$this->logger->warning(
				'Mapping "' . $mapping->name->getText() . '" has an unresolvable predicate for property "'
				. $statement->getPropertyName()->text . '"; skipping it.'
			);
			return [];
		}

		if ( $statement->getPropertyType() === RelationType::NAME ) {
			return $this->projectRelationStatement( $statement, $predicate, $subjectIri, $graph );
		}

		return $this->projectLiteralStatement( $statement, $propertyMapping, $predicate, $expander, $subjectIri, $graph, $mapping );
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
	private function projectLiteralStatement(
		Statement $statement,
		PropertyMapping $propertyMapping,
		Iri $predicate,
		CurieExpander $expander,
		Iri $subjectIri,
		Iri $graph,
		Mapping $mapping
	): array {
		$literals = $this->valueMappers->mapValue( $statement->getPropertyType(), $statement->getValue() );

		if ( $literals === null ) {
			return [];
		}

		$quads = [];

		foreach ( $literals as $literal ) {
			$object = $this->applyOverrides( $literal, $propertyMapping, $expander, $mapping, $statement->getPropertyName()->text );
			$quads[] = new Quad( $subjectIri, $predicate, $object, $graph );
		}

		return $quads;
	}

	/**
	 * Applies the optional datatype override or language tag. A datatype override wins over a language
	 * tag (an RDF literal cannot carry both); the validator rejects a Mapping that sets both. A language
	 * tag applies only to a plain string literal — for a typed literal (number, date, …) it is ignored,
	 * since the writer's schema already fixed the datatype.
	 *
	 * The language tag is re-validated here as well as at save time: a Mapping created outside the
	 * save-time validator (importDump, a page authored before validation existed) could carry a
	 * malformed tag, which would corrupt the serialized literal. An invalid tag is therefore dropped —
	 * the plain string literal is emitted and a warning logged — so a bad stored Mapping degrades the
	 * output instead of aborting the whole export.
	 */
	private function applyOverrides(
		Literal $literal,
		PropertyMapping $propertyMapping,
		CurieExpander $expander,
		Mapping $mapping,
		string $propertyName
	): Literal {
		if ( $propertyMapping->datatype !== null ) {
			$datatype = $expander->expand( $propertyMapping->datatype );

			return $datatype === null ? $literal : new Literal( $literal->lexicalForm, $datatype );
		}

		if ( $propertyMapping->language !== null && $this->isPlainString( $literal ) ) {
			return $this->withLanguageTag( $literal, $propertyMapping->language, $mapping, $propertyName );
		}

		return $literal;
	}

	private function withLanguageTag( Literal $literal, string $language, Mapping $mapping, string $propertyName ): Literal {
		if ( !Literal::isValidLanguageTag( $language ) ) {
			$this->logger->warning(
				'Mapping "' . $mapping->name->getText() . '" has an invalid language tag "' . $language
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
