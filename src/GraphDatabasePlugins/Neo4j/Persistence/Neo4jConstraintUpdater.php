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
		$this->queryEngine->runWriteQuery(
			'CREATE CONSTRAINT `Page wiki_id id` IF NOT EXISTS FOR (node:Page) REQUIRE (node.wiki_id, node.id) IS UNIQUE'
		);

		// Every projected Subject id is a bare nanoid, globally unique on its own: a page's slot holds
		// local Subjects only (ADR 23), and a relation to another Source gets no edge and no stub node.
		// Namespacing is deferred until something puts a qualified id in the graph.
		$this->queryEngine->runWriteQuery(
			'CREATE CONSTRAINT `Subject id` IF NOT EXISTS FOR (node:Subject) REQUIRE (node.id) IS UNIQUE'
		);

		// Lets the Subject counts read the wiki's own Subjects instead of every Subject in a shared graph.
		$this->queryEngine->runWriteQuery(
			'CREATE INDEX `Subject wiki_id` IF NOT EXISTS FOR (node:Subject) ON (node.wiki_id)'
		);
	}

}
