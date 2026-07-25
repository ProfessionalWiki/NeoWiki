<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

/**
 * The subjects a write transaction may have orphaned, collected as it goes.
 *
 * A node can only lose its last incoming relation if this write deleted an edge pointing at it, so the
 * former targets of the deleted edges are the complete candidate set. One instance is shared by every
 * step of a single transaction and read once at its end.
 */
class Neo4jOrphanCandidates {

	/**
	 * @var array<string, true> Keyed by subject id to deduplicate.
	 */
	private array $subjectIds = [];

	public function add( string ...$subjectIds ): void {
		foreach ( $subjectIds as $subjectId ) {
			$this->subjectIds[$subjectId] = true;
		}
	}

	/**
	 * @return string[]
	 */
	public function getSubjectIds(): array {
		return array_keys( $this->subjectIds );
	}

}
