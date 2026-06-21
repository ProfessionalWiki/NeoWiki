<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

class Neo4jConstraintUpdater {

	public function __construct(
		private Neo4jWriteQueryEngine $queryEngine
	) {
	}

	public function createDefaultConstraints(): void {
		// Page identity is scoped per wiki: pages from different wikis may share an id
		// in a shared graph, so uniqueness is on the (wiki_id, id) pair rather than id alone.
		$this->createNodePropertyUniquenessConstraint( 'Page', [ 'wiki_id', 'id' ] );

		// Subject ids are bare nanoids that are globally unique; namespacing is deferred.
		$this->createNodePropertyUniquenessConstraint( 'Subject', [ 'id' ] );
	}

	/**
	 * @param string[] $propertyNames
	 */
	private function createNodePropertyUniquenessConstraint( string $nodeLabel, array $propertyNames ): void {
		$properties = implode(
			', ',
			array_map(
				static fn ( string $propertyName ): string => 'node.' . Cypher::escape( $propertyName ),
				$propertyNames
			)
		);

		$this->queryEngine->runWriteQuery(
			'CREATE CONSTRAINT ' . Cypher::escape( $nodeLabel . ' ' . implode( ' ', $propertyNames ) ) . '
			 IF NOT EXISTS
			 FOR (node:' . Cypher::escape( $nodeLabel ) . ')
			 REQUIRE (' . $properties . ') IS UNIQUE'
		);
	}

}
