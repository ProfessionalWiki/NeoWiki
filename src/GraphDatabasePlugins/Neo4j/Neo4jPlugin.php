<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jClientReadQueryEngine;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jClientWriteQueryEngine;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jReadQueryEngine;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jProjectionStore;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectUpdaterFactory;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jWriteQueryEngine;
use Psr\Log\LoggerInterface;

/**
 * Composition root for the Neo4j graph-database backend: owns the assembly of the
 * projection store and read/write query engines from the dependencies core injects.
 * A new backend copies this shape.
 */
readonly class Neo4jPlugin {

	private GraphDatabasePlugin $projectionStore;
	private Neo4jReadQueryEngine $readQueryEngine;
	private Neo4jWriteQueryEngine $writeQueryEngine;

	public function __construct(
		ClientInterface $client,
		ClientInterface $readOnlyClient,
		SchemaLookup $schemaLookup,
		Neo4jValueBuilderRegistry $valueBuilderRegistry,
		LoggerInterface $logger,
		string $wikiId,
	) {
		$this->projectionStore = new Neo4jProjectionStore(
			client: $client,
			subjectUpdaterFactory: new Neo4jSubjectUpdaterFactory(
				schemaLookup: $schemaLookup,
				valueBuilderRegistry: $valueBuilderRegistry,
				logger: $logger,
				wikiId: $wikiId,
			),
			wikiId: $wikiId,
		);
		$this->readQueryEngine = new Neo4jClientReadQueryEngine( $readOnlyClient );
		$this->writeQueryEngine = new Neo4jClientWriteQueryEngine( $client );
	}

	public function getGraphDatabasePlugin(): GraphDatabasePlugin {
		return $this->projectionStore;
	}

	public function getReadQueryEngine(): Neo4jReadQueryEngine {
		return $this->readQueryEngine;
	}

	public function getWriteQueryEngine(): Neo4jWriteQueryEngine {
		return $this->writeQueryEngine;
	}

}
