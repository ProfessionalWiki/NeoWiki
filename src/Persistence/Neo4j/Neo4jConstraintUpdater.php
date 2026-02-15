<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

class Neo4jConstraintUpdater {

	public function __construct(
		private WriteQueryEngine $queryEngine
	) {
	}

	public function createDefaultConstraints(): void {
		$this->createNodePropertyConstraint( 'Page', 'id' );
		$this->createNodePropertyConstraint( 'Subject', 'id' );
	}

	private function createNodePropertyConstraint( string $nodeLabel, string $propertyName ): void {
		$this->queryEngine->runWriteQuery(
			'CREATE CONSTRAINT ' . Cypher::escape( $nodeLabel . ' ' . $propertyName ) . '
			 IF NOT EXISTS
			 FOR (node:' . Cypher::escape( $nodeLabel ) . ')
			 REQUIRE node.' . Cypher::escape( $propertyName ) . ' IS UNIQUE'
		);
	}

}
