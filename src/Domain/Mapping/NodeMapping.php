<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

/**
 * One intermediate node a {@see SchemaMapping} synthesizes: the event-style node that event-centric
 * ontologies mediate a relationship through and that has no counterpart in NeoWiki's data
 * (OntologyMapping.md § What makes this hard). The node key it is declared under names it from the
 * property entries that attach to it and from another node's `$parent`, so it is not repeated here.
 *
 * The CURIEs are expanded against the Mapping's page-level prefixes by a {@see CurieExpander} at
 * validation and projection time.
 */
readonly class NodeMapping {

	/**
	 * @param string $class The `rdf:type` given to each instance of the node.
	 * @param string $linkPredicate The predicate of the triple from the anchor — the parent node's
	 *   instance, or the Subject when there is no parent — to the node instance.
	 * @param string|null $parent The key of the node this one hangs off, instead of the Subject.
	 */
	public function __construct(
		public string $class,
		public string $linkPredicate,
		public ?string $parent = null,
		public NodeScope $scope = NodeScope::Subject,
	) {
	}

}
