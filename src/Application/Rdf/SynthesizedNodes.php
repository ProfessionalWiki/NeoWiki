<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Quad;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * The synthesized intermediate-node instances of one Subject's projection: it mints their IRIs and, for
 * the instances that ended up carrying something, the triples that put them in the graph.
 *
 * Instances are minted on demand and their scaffolding — the `rdf:type` triple and the link triple from
 * the anchor (the parent node's instance, or the Subject) — is recorded at the same moment, so a node is
 * in the output only when a value attached to it or to something below it. Asking for an instance also
 * pulls in its parent chain, which is how a nested node brings its ancestors along. Nothing is emitted
 * for a node no value reached, so an event ontology's projection carries no empty event nodes.
 */
class SynthesizedNodes {

	/**
	 * @var array<string, true> Keyed by the IRI of every instance already scaffolded, so a node shared by
	 *   several properties is scaffolded once.
	 */
	private array $scaffolded = [];

	/** @var list<Quad> */
	private array $scaffold = [];

	/**
	 * @param array<string, SynthesizedNode> $nodes The resolvable nodes of the Subject's Schema entry,
	 *   keyed by node key.
	 */
	public function __construct(
		private readonly array $nodes,
		private readonly SubjectId $subjectId,
		private readonly Iri $subjectIri,
		private readonly Iri $graph,
		private readonly RdfNamespaces $namespaces,
	) {
	}

	/**
	 * The node a property entry's key names, or null when it was not declared or could not be resolved.
	 */
	public function get( string $key ): ?SynthesizedNode {
		return $this->nodes[$key] ?? null;
	}

	/**
	 * The single instance a subject-scoped node has on this Subject.
	 */
	public function instance( SynthesizedNode $node ): Iri {
		return $this->record( $node, $this->namespaces->synthesizedNode( $this->subjectId, ...$node->keyPath ) );
	}

	/**
	 * The instance a value-scoped node has for one relation value. Anchoring it on the Relation's
	 * persistent ID keeps the node the same across projections and re-projections.
	 */
	public function relationInstance( SynthesizedNode $node, RelationId $relationId ): Iri {
		return $this->record( $node, $this->namespaces->synthesizedRelationNode( $relationId ) );
	}

	/**
	 * The instance a value-scoped node has for one literal value, which has no persistent ID of its own
	 * and is therefore identified by its position among the property's values.
	 */
	public function valueInstance( SynthesizedNode $node, int $position ): Iri {
		return $this->record(
			$node,
			$this->namespaces->synthesizedNode( $this->subjectId, $node->key(), (string)$position )
		);
	}

	private function record( SynthesizedNode $node, Iri $instance ): Iri {
		if ( isset( $this->scaffolded[$instance->value] ) ) {
			return $instance;
		}

		// Recorded before resolving the anchor, so a parent cycle that survived node resolution
		// terminates here instead of recursing forever.
		$this->scaffolded[$instance->value] = true;

		$anchor = $this->anchor( $node );

		$this->scaffold[] = new Quad( $instance, $this->namespaces->rdfType(), $node->class, $this->graph );
		$this->scaffold[] = new Quad( $anchor, $node->linkPredicate, $instance, $this->graph );

		return $instance;
	}

	/**
	 * What the node's link triple runs from: its parent's instance, or the Subject when it has no parent.
	 * A parent is always subject-scoped, so it has exactly one instance. Reaching for it records it, which
	 * is what makes a whole ancestor chain appear when only its deepest node carries a value. Node
	 * resolution drops a node whose parent is missing or unusable, so a resolved node's parent is here.
	 */
	private function anchor( SynthesizedNode $node ): Iri {
		$parentKey = $node->parentKey();

		return $parentKey === null ? $this->subjectIri : $this->instance( $this->nodes[$parentKey] );
	}

	/**
	 * @return list<Quad>
	 */
	public function scaffoldQuads(): array {
		return $this->scaffold;
	}

}
