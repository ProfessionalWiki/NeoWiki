<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectUpdaterFactory;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;
use Psr\Log\LoggerInterface;

/**
 * Composition root for the Neo4j graph-database backend: owns the assembly of the
 * Neo4jQueryStore from the dependencies core injects. A new backend copies this shape.
 */
readonly class Neo4jPlugin {

	private Neo4jQueryStore $queryStore;

	public function __construct(
		ClientInterface $client,
		ClientInterface $readOnlyClient,
		SchemaLookup $schemaLookup,
		Neo4jValueBuilderRegistry $valueBuilderRegistry,
		LoggerInterface $logger,
		string $wikiId,
	) {
		$this->queryStore = new Neo4jQueryStore(
			client: $client,
			readOnlyClient: $readOnlyClient,
			subjectUpdaterFactory: new Neo4jSubjectUpdaterFactory(
				schemaLookup: $schemaLookup,
				valueBuilderRegistry: $valueBuilderRegistry,
				logger: $logger,
				wikiId: $wikiId,
			),
			wikiId: $wikiId,
		);
	}

	public function getGraphDatabasePlugin(): GraphDatabasePlugin {
		return $this->queryStore;
	}

	public function getQueryStore(): Neo4jQueryStore {
		return $this->queryStore;
	}

}
