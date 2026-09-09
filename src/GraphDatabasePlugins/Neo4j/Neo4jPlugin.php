<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\EntryPoints\ParserAuthority;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\CompositeCypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\ExplainCypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\KeywordCypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jReadQueryEngine;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\Lua\CypherQueryRunner;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction\CypherRawParserFunction;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jClientReadQueryEngine;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jConstraintUpdater;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jProjectionStore;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectUpdaterFactory;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jWriteQueryEngine;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedRawQueryAuthorizer;
use Psr\Log\LoggerInterface;

/**
 * Composition root for the Neo4j graph-database backend: owns the assembly of the
 * projection store and read/write query engines from the dependencies core injects.
 * A new backend copies this shape.
 */
readonly class Neo4jPlugin {

	/**
	 * The name identifying this backend among the configured graph stores: what a scoped rebuild is
	 * addressed by, and what its run records are filed under. There is at most one Neo4j backend per
	 * wiki, so the name is a constant rather than configuration.
	 */
	public const STORE_NAME = 'neo4j';

	private GraphDatabasePlugin $projectionStore;
	private Neo4jReadQueryEngine $readQueryEngine;
	private Neo4jWriteQueryEngine $writeQueryEngine;
	private ClientInterface $readOnlyClient;

	public function __construct(
		ClientInterface $client,
		ClientInterface $readOnlyClient,
		SchemaLookup $schemaLookup,
		Neo4jValueBuilderRegistry $valueBuilderRegistry,
		LoggerInterface $logger,
		string $wikiId,
	) {
		$this->writeQueryEngine = new Neo4jWriteQueryEngine( $client );
		$this->projectionStore = new Neo4jProjectionStore(
			client: $client,
			subjectUpdaterFactory: new Neo4jSubjectUpdaterFactory(
				schemaLookup: $schemaLookup,
				valueBuilderRegistry: $valueBuilderRegistry,
				logger: $logger,
				wikiId: $wikiId,
			),
			// Reuse the write engine the plugin already owns so initialize() creates constraints through it.
			constraintUpdater: new Neo4jConstraintUpdater( $this->writeQueryEngine ),
			wikiId: $wikiId,
		);
		$this->readQueryEngine = new Neo4jClientReadQueryEngine( $readOnlyClient );
		$this->readOnlyClient = $readOnlyClient;
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

	/**
	 * The Lua library function names this plugin contributes to mw.neowiki. The handler for each
	 * lives on ScribuntoLuaLibrary (it needs LibraryBase services); the plugin's presence is the gate.
	 *
	 * @return string[]
	 */
	public function getLuaLibraryFunctionNames(): array {
		return [ 'query' ];
	}

	public function registerParserFunctions( Parser $parser ): void {
		$parser->setFunctionHook(
			'cypher_raw',
			function ( Parser $parser, string $cypherQuery ): array {
				return $this->newRawParserFunction( $parser )->handle( $parser, $cypherQuery );
			}
		);
	}

	public function newRawParserFunction( Parser $parser ): CypherRawParserFunction {
		return new CypherRawParserFunction( $this->newParseTimeQueryService( $parser ), Neo4jQueryLimits::defaultTier() );
	}

	public function newLuaQueryRunner( Parser $parser ): CypherQueryRunner {
		return new CypherQueryRunner( $this->newParseTimeQueryService( $parser ), Neo4jQueryLimits::defaultTier() );
	}

	/**
	 * Parse-time queries run as the user the page is parsed for, and always at the default tier: the
	 * output is parser-cached under that user's access class, and may vary by nothing else.
	 */
	private function newParseTimeQueryService( Parser $parser ): Neo4jQueryService {
		return $this->newQueryService( ParserAuthority::of( $parser ) );
	}

	public function newQueryService( Authority $authority ): Neo4jQueryService {
		return new Neo4jQueryService(
			$this->readQueryEngine,
			new CompositeCypherQueryValidator( [
				new KeywordCypherQueryValidator(),
				new ExplainCypherQueryValidator( $this->readOnlyClient ),
			] ),
			new Neo4jResultNormalizer(),
			new AuthorityBasedRawQueryAuthorizer( $authority ),
		);
	}

}
