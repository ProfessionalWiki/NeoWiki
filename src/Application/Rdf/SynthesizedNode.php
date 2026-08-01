<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeScope;
use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;

/**
 * A {@see NodeMapping} whose terms have been expanded and whose place in the node tree is known, ready
 * for {@see SynthesizedNodes} to mint instances of. A node that cannot be resolved — an unusable class or
 * link predicate, a parent that is missing, unusable, per value, or forms a cycle — has no
 * {@see SynthesizedNode} at all, which is what drops its whole subtree from the projection.
 */
readonly class SynthesizedNode {

	/**
	 * @param non-empty-list<string> $keyPath The node keys from the Subject down to this node, its own
	 *   key last. Subject-anchored instance IRIs are built from it, so nesting is visible in the IRI, and
	 *   it is where the node's own key and its parent's come from.
	 */
	public function __construct(
		public Iri $class,
		public Iri $linkPredicate,
		public NodeScope $scope,
		public array $keyPath,
	) {
	}

	public function key(): string {
		return $this->keyPath[count( $this->keyPath ) - 1];
	}

	public function parentKey(): ?string {
		return count( $this->keyPath ) > 1 ? $this->keyPath[count( $this->keyPath ) - 2] : null;
	}

}
