<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use LogicException;
use ProfessionalWiki\NeoWiki\Domain\Mapping\CurieExpander;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeScope;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SchemaMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SubjectMapping;
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
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use Psr\Log\LoggerInterface;

/**
 * Projects a {@see Page} into a target ontology using one page-level {@see Mapping} (OntologyMapping.md).
 * The Mapping page's name is the projection target; it holds one entry per mapped Schema. Per Subject
 * whose Schema has an entry it emits `rdf:type <mapped class>`, `rdfs:label` (always), and one triple per
 * mapped property value — mapping only the vocabulary, because the Subject IRI stays native
 * (`neo-subj:`), the entity being the wiki's own. Unmapped properties are absent, so the output is
 * conformant to the target ontology.
 *
 * On top of that term substitution it performs both structural transformations
 * (OntologyMapping.md § What makes this hard):
 *
 *  - **Expansion.** A property entry may attach its values to a synthesized intermediate node instead of
 *    to the Subject, so an event-centric target gets the `E67_Birth` / `E12_Production` node its paths
 *    run through. {@see SynthesizedNodes} mints those nodes, and only where a value reached them.
 *  - **Contraction.** A Schema entry may contribute its own values to the Subjects it points at, so a
 *    Subject that models an event explicitly still lands as flat properties on a flat target. This is
 *    source-side: the contributing page emits the triples into its own graph, and no other page is read.
 *
 * There is no `neo:Relation` reification; a relation value becomes a direct triple to the target
 * Subject's native IRI, which may be untyped when that Subject has no entry of its own.
 *
 * Every quad is placed in the page's named graph for this projection's target (`{$base}/graph/{target}/page/{id}`,
 * #1053), so the per-page sync used by the native projection (NativeRdfProjection.md) works for an ontology
 * store too, and the native and ontology projections of a page can share one store without colliding. No
 * page-metadata triples are emitted.
 */
class OntologyMappingProjector implements PageProjector {

	private readonly string $target;
	private readonly CurieExpander $expander;

	/** @var array<string, array<string, SynthesizedNode>> The resolved nodes of each Schema entry reached. */
	private array $resolvedNodes = [];

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
	 * {@see projectPage()} (mapped type, label, mapped property values with the nodes they are attached
	 * to, relations as direct triples, and what it contributes to other Subjects) placed in the target's
	 * named graph. A Subject that is not on the page, or whose Schema has no entry in this Mapping,
	 * yields an empty list.
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
		$schemaName = $subject->getSchemaName()->getText();

		$quads = $schemaMapping->subject === null
			? []
			: $this->ownQuads( $subject, $schemaName, $schemaMapping, $schemaMapping->subject, $graph );

		foreach ( $schemaMapping->contributions as $relationName => $properties ) {
			// A numeric relation name becomes an int array key, so it is cast where it is consumed.
			$quads = array_merge( $quads, $this->contributionQuads( $subject, (string)$relationName, $properties, $graph ) );
		}

		return $quads;
	}

	/**
	 * What the Subject says about itself: its type and label, plus its mapped property values, on the
	 * Subject or on the synthesized node each is attached to.
	 *
	 * @return Quad[]
	 */
	private function ownQuads(
		Subject $subject,
		string $schemaName,
		SchemaMapping $schemaMapping,
		SubjectMapping $subjectMapping,
		Iri $graph
	): array {
		$subjectIri = $this->namespaces->subject( $subject->id );
		$nodes = new SynthesizedNodes(
			$this->resolveNodes( $schemaName, $schemaMapping ),
			$subject->id,
			$subjectIri,
			$graph,
			$this->namespaces
		);

		$quads = $this->headQuads( $subject, $subjectMapping, $subjectIri, $graph );

		foreach ( $subject->getStatements()->asArray() as $statement ) {
			$propertyMapping = $schemaMapping->properties->get( $statement->getPropertyName()->text );

			if ( $propertyMapping !== null ) {
				$quads = array_merge(
					$quads,
					$this->statementQuads( $statement, $propertyMapping, $nodes, $subjectIri, $graph )
				);
			}
		}

		// Last, because a node is scaffolded by the statements that reached it.
		return array_merge( $quads, $nodes->scaffoldQuads() );
	}

	/**
	 * The type and label triples. `rdfs:label` is always emitted; a `labelPredicate` adds the target
	 * ontology's own label term for the same text rather than replacing it.
	 *
	 * @return Quad[]
	 */
	private function headQuads( Subject $subject, SubjectMapping $subjectMapping, Iri $subjectIri, Iri $graph ): array {
		$quads = [];
		$class = $this->expander->expand( $subjectMapping->class );

		if ( $class === null ) {
			// Cannot happen for a Mapping that passed save-time validation; guard the projection anyway.
			$this->logger->warning(
				'Mapping "' . $this->target . '" has an unresolvable subject class for Schema "'
				. $subject->getSchemaName()->getText() . '"; skipping the type triple.'
			);
		}
		else {
			$quads[] = new Quad( $subjectIri, $this->namespaces->rdfType(), $class, $graph );
		}

		$label = RdfLiteralFactory::typed( $subject->label->text, 'string' );
		$quads[] = new Quad( $subjectIri, $this->namespaces->rdfsLabel(), $label, $graph );

		if ( $subjectMapping->labelPredicate !== null ) {
			$predicate = $this->expander->expand( $subjectMapping->labelPredicate );

			if ( $predicate === null ) {
				$this->logger->warning(
					'Mapping "' . $this->target . '" has an unresolvable label predicate for Schema "'
					. $subject->getSchemaName()->getText() . '"; skipping the extra label triple.'
				);
			}
			else {
				$quads[] = new Quad( $subjectIri, $predicate, $label, $graph );
			}
		}

		return $quads;
	}

	/**
	 * @return Quad[]
	 */
	private function statementQuads(
		Statement $statement,
		PropertyMapping $propertyMapping,
		SynthesizedNodes $nodes,
		Iri $subjectIri,
		Iri $graph
	): array {
		$predicate = $this->expandPredicate( $propertyMapping, $statement );

		if ( $predicate === null ) {
			return [];
		}

		if ( $propertyMapping->node === null ) {
			return $this->quadsOn( $subjectIri, $predicate, $this->objectTerms( $statement, $propertyMapping ), $graph );
		}

		$node = $nodes->get( $propertyMapping->node );

		if ( $node === null ) {
			$this->logger->warning(
				'Mapping "' . $this->target . '" attaches property "' . $statement->getPropertyName()->text
				. '" to node "' . $propertyMapping->node . '", which is not usable; skipping the property.'
			);
			return [];
		}

		if ( $node->scope === NodeScope::Value ) {
			return $this->perValueNodeQuads( $statement, $propertyMapping, $predicate, $node, $nodes, $graph );
		}

		$objects = $this->objectTerms( $statement, $propertyMapping );

		// The instance is minted only once there is something to hang off it, so a Subject with no value
		// for any of a node's properties gets no empty node.
		return $objects === [] ? [] : $this->quadsOn( $nodes->instance( $node ), $predicate, $objects, $graph );
	}

	/**
	 * A value-scoped node gets one instance per value of the property attached to it: the CIDOC-CRM
	 * `E12_Production` each Creator of an Artwork carries out. A relation value's instance is identified
	 * by the Relation's persistent ID, a literal value's by its position among the property's values.
	 *
	 * @return Quad[]
	 */
	private function perValueNodeQuads(
		Statement $statement,
		PropertyMapping $propertyMapping,
		Iri $predicate,
		SynthesizedNode $node,
		SynthesizedNodes $nodes,
		Iri $graph
	): array {
		$quads = [];

		if ( $statement->getPropertyType() === RelationType::NAME ) {
			foreach ( $this->relationsOf( $statement ) as $relation ) {
				$quads[] = new Quad(
					$nodes->relationInstance( $node, $relation->id ),
					$predicate,
					$this->namespaces->subject( $relation->targetId ),
					$graph
				);
			}

			return $quads;
		}

		foreach ( $this->objectTerms( $statement, $propertyMapping ) as $position => $object ) {
			$quads[] = new Quad( $nodes->valueInstance( $node, $position ), $predicate, $object, $graph );
		}

		return $quads;
	}

	/**
	 * Contraction: this Subject's own values, emitted about each Subject its named relation points at,
	 * into this page's named graph. Every target of a multi-valued relation receives them — twins sharing
	 * one Birth each get its date. A Subject with no value for the relation contributes nothing, which is
	 * the normal shape of optional data; a property that is not relation-typed at all is a mismatch
	 * between the Mapping and the Schema and is logged.
	 *
	 * @return Quad[]
	 */
	private function contributionQuads(
		Subject $subject,
		string $relationName,
		PropertyMappings $properties,
		Iri $graph
	): array {
		$relationStatement = $subject->getStatements()->getStatement( new PropertyName( $relationName ) );

		if ( $relationStatement === null ) {
			return [];
		}

		if ( $relationStatement->getPropertyType() !== RelationType::NAME ) {
			$this->logger->warning(
				'Mapping "' . $this->target . '" contributes through relation "' . $relationName
				. '", which Subject "' . $subject->id->text . '" does not hold as a relation; skipping the contribution.'
			);
			return [];
		}

		$contributed = $this->contributedTerms( $subject, $properties );
		$quads = [];

		foreach ( $this->relationsOf( $relationStatement ) as $relation ) {
			$target = $this->namespaces->subject( $relation->targetId );

			foreach ( $contributed as [ $predicate, $objects ] ) {
				$quads = array_merge( $quads, $this->quadsOn( $target, $predicate, $objects, $graph ) );
			}
		}

		return $quads;
	}

	/**
	 * The predicate and objects each contributed property produces. Neither depends on which Subject
	 * receives them, so they are computed once and reused for every target of the relation.
	 *
	 * @return list<array{Iri, list<RdfTerm>}>
	 */
	private function contributedTerms( Subject $subject, PropertyMappings $properties ): array {
		$contributed = [];

		foreach ( $subject->getStatements()->asArray() as $statement ) {
			$propertyMapping = $properties->get( $statement->getPropertyName()->text );

			if ( $propertyMapping === null ) {
				continue;
			}

			$predicate = $this->expandPredicate( $propertyMapping, $statement );

			if ( $predicate !== null ) {
				$contributed[] = [ $predicate, $this->objectTerms( $statement, $propertyMapping ) ];
			}
		}

		return $contributed;
	}

	/**
	 * @param list<RdfTerm> $objects
	 * @return Quad[]
	 */
	private function quadsOn( Iri $anchor, Iri $predicate, array $objects, Iri $graph ): array {
		return array_map(
			static fn ( RdfTerm $object ): Quad => new Quad( $anchor, $predicate, $object, $graph ),
			$objects
		);
	}

	private function expandPredicate( PropertyMapping $propertyMapping, Statement $statement ): ?Iri {
		$predicate = $this->expander->expand( $propertyMapping->predicate );

		if ( $predicate === null ) {
			$this->logger->warning(
				'Mapping "' . $this->target . '" has an unresolvable predicate for property "'
				. $statement->getPropertyName()->text . '"; skipping it.'
			);
		}

		return $predicate;
	}

	/**
	 * The objects a statement's values become: each relation target's native Subject IRI, or the mapped
	 * literal (or IRI, for a url value) each value produces.
	 *
	 * @return list<RdfTerm>
	 */
	private function objectTerms( Statement $statement, PropertyMapping $propertyMapping ): array {
		if ( $statement->getPropertyType() === RelationType::NAME ) {
			return array_map(
				fn ( Relation $relation ): Iri => $this->namespaces->subject( $relation->targetId ),
				$this->relationsOf( $statement )
			);
		}

		$terms = $this->valueMappers->mapValue( $statement->getPropertyType(), $statement->getValue() );

		if ( $terms === null ) {
			return [];
		}

		return array_values( array_map(
			fn ( RdfTerm $term ): RdfTerm => $this->applyOverrides( $term, $propertyMapping, $statement->getPropertyName()->text ),
			$terms
		) );
	}

	/**
	 * @return list<Relation>
	 */
	private function relationsOf( Statement $statement ): array {
		$value = $statement->getValue();

		return $value instanceof RelationValue ? array_values( $value->relations ) : [];
	}

	/**
	 * The Schema entry's usable nodes. Resolution is per Schema entry rather than per Subject, so a page
	 * holding fifty Subjects of one Schema expands its nodes once and logs each problem once.
	 *
	 * @return array<string, SynthesizedNode> Keyed by node key.
	 */
	private function resolveNodes( string $schemaName, SchemaMapping $schemaMapping ): array {
		$this->resolvedNodes[$schemaName] ??= $this->newResolvedNodes( $schemaName, $schemaMapping );

		return $this->resolvedNodes[$schemaName];
	}

	/**
	 * Expands each declared node's terms and places it in the node tree, dropping the ones the projection
	 * cannot use. A node whose class or link predicate does not expand safely is dropped, and so is
	 * everything below it: without its anchor there is nowhere for its descendants' triples to hang. A
	 * parent chain that is broken, cyclic, or runs through a per-value node — impossible for a Mapping
	 * that passed save-time validation, reachable through import — drops the node the same way.
	 *
	 * @return array<string, SynthesizedNode> Keyed by node key.
	 */
	private function newResolvedNodes( string $schemaName, SchemaMapping $schemaMapping ): array {
		/** @var array<string, array{class: Iri, linkPredicate: Iri, mapping: NodeMapping}> $expanded */
		$expanded = [];

		foreach ( $schemaMapping->nodes as $key => $node ) {
			// An all-digit node key is an int array key by the time json_decode is done with it, so the
			// cast has to happen where the key is consumed rather than where it was stored.
			$key = (string)$key;
			$class = $this->expander->expand( $node->class );
			$linkPredicate = $this->expander->expand( $node->linkPredicate );

			if ( $class === null || $linkPredicate === null ) {
				$this->logger->warning(
					'Mapping "' . $this->target . '" has an unresolvable class or link predicate for node "'
					. $key . '" of Schema "' . $schemaName . '"; skipping the node.'
				);
				continue;
			}

			$expanded[$key] = [ 'class' => $class, 'linkPredicate' => $linkPredicate, 'mapping' => $node ];
		}

		$resolved = [];

		foreach ( $expanded as $key => $node ) {
			$key = (string)$key;
			$keyPath = self::keyPath( $key, $expanded );

			if ( $keyPath === null ) {
				$this->logger->warning(
					'Mapping "' . $this->target . '" has an unusable parent chain for node "' . $key
					. '" of Schema "' . $schemaName . '"; skipping the node.'
				);
				continue;
			}

			$resolved[$key] = new SynthesizedNode(
				class: $node['class'],
				linkPredicate: $node['linkPredicate'],
				scope: $node['mapping']->scope,
				linkDirection: $node['mapping']->linkDirection,
				keyPath: $keyPath,
			);
		}

		return $resolved;
	}

	/**
	 * The node keys from the Subject down to this node, or null when the chain cannot anchor it: a parent
	 * that is undeclared or was itself dropped, a parent that is per value and so has no single instance
	 * to hang a child off, or a loop.
	 *
	 * @param array<string, array{class: Iri, linkPredicate: Iri, mapping: NodeMapping}> $expanded
	 * @return non-empty-list<string>|null The node's own key is always the last element.
	 */
	private static function keyPath( string $key, array $expanded ): ?array {
		$path = [];
		$seen = [];
		$current = $key;

		while ( $current !== null ) {
			if ( isset( $seen[$current] ) || !array_key_exists( $current, $expanded ) ) {
				return null;
			}

			$node = $expanded[$current]['mapping'];

			if ( $path !== [] && $node->scope === NodeScope::Value ) {
				return null;
			}

			$seen[$current] = true;
			array_unshift( $path, $current );
			$current = $node->parent;
		}

		return $path;
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
