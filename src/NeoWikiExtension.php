<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use Exception;
use LogicException;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\Response;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Session\CsrfTokenSet;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;
use ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject\SetMainSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject\SetMainSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering\SetSubjectsOrderingAction;
use ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering\SetSubjectsOrderingPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject\ReplaceSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\ReplaceSubject\ReplaceSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetLayout\GetLayoutPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetLayout\GetLayoutQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects\GetPageSubjectsQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubject\ValidateSubjectQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubjectUpdate\ValidateSubjectUpdateQuery;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionIdGenerator;
use ProfessionalWiki\NeoWiki\Persistence\CorePagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\CompositeGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\FailureIsolatingGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphBackendNotConfiguredException;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePluginRegistry;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Application\NullSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Application\LayoutLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectPermissionHints;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Application\Mappings;
use ProfessionalWiki\NeoWiki\Application\Rdf\OntologyMappingProjector;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageExporter;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageLoader;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfProjection;
use ProfessionalWiki\NeoWiki\Domain\Mapping\CurieExpander;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfSerializer;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\NeoWiki\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ExportPageRdfApi;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jWriteQueryEngine;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetPageSubjectsApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetLayoutApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetLayoutSummariesApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaNamesApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaSummariesApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectLabelsApi;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\REST\CypherQueryApi;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\REST\Neo4jRouteRegistration;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ReplaceSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SetMainSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SetSubjectsOrderingApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ValidateSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ValidateSubjectUpdateApi;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectContentRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\PointInTimeSubjectLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingMappingLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseMappingNameLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MappingPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageMappingLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageLayoutLookup;
use ProfessionalWiki\NeoWiki\Persistence\MappingNameLookup;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Neo4jPlugin;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;
use ProfessionalWiki\NeoWiki\Persistence\SchemaNameLookup;
use ProfessionalWiki\NeoWiki\Persistence\LayoutNameLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseLayoutNameLookup;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Presentation\FrontendModuleLoader;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
use ProfessionalWiki\NeoWiki\Presentation\LayoutPresentationSerializer;
use Wikimedia\Rdbms\IDatabase;

class NeoWikiExtension {

	public const int NS_SCHEMA = 7474;
	public const int NS_LAYOUT = 7476;
	public const int NS_MAPPING = 7478;

	public const string PROJECTION_NATIVE = 'native';

	private PropertyTypeRegistry $propertyTypeRegistry;
	private PagePropertyProviderRegistry $pagePropertyProviderRegistry;
	private Neo4jValueBuilderRegistry $valueBuilderRegistry;
	private RdfValueMapperRegistry $rdfValueMapperRegistry;
	private bool $extensionsRegistered = false;
	private SubjectRepository $subjectRepository;
	private CompositeGraphDatabasePlugin $graphDatabasePlugin;
	private CompositeGraphDatabasePlugin $isolatingGraphDatabasePlugin;
	private GraphDatabasePluginRegistry $graphDatabasePluginRegistry;
	private ?Neo4jPlugin $neo4jPlugin = null;
	private ClientInterface $neo4jClient;
	private ClientInterface $readOnlyNeo4jClient;
	private static ?self $instance = null;

	public static function getInstance(): self {
		self::$instance ??= new self(
			( new NeoWikiConfigFactory() )->buildFromMediaWikiConfig( MediaWikiServices::getInstance()->getMainConfig() )
		);

		return self::$instance;
	}

	// Test seam: NeoWikiExtension caches config-derived state in a singleton with no other reset.
	// Tests that vary graph-backend config must rebuild it. See NeoWikiIntegrationTestCase::runWithoutGraphBackend().
	public static function resetInstance(): void {
		self::$instance = null;
	}

	public static function onExtensionRegistration(): void {
		// This registration-time gate must agree with NeoWikiConfig::hasNeo4jBackend() at request time,
		// so both resolve the URLs through the shared NeoWikiConfigFactory::resolveReadUrl/resolveWriteUrl helpers.
		$readUrl = NeoWikiConfigFactory::resolveReadUrl(
			is_string( $GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] ?? null ) ? $GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] : null
		);
		$writeUrl = NeoWikiConfigFactory::resolveWriteUrl(
			is_string( $GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] ?? null ) ? $GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] : null
		);

		$GLOBALS['wgRestAPIAdditionalRouteFiles'] = array_merge(
			$GLOBALS['wgRestAPIAdditionalRouteFiles'] ?? [],
			Neo4jRouteRegistration::routeFiles( $readUrl, $writeUrl )
		);
	}

	private function __construct(
		public readonly NeoWikiConfig $config
	) {
	}

	public function getPropertyTypeRegistry(): PropertyTypeRegistry {
		if ( !isset( $this->propertyTypeRegistry ) ) {
			$this->propertyTypeRegistry = PropertyTypeRegistry::withCoreTypes();
		}

		$this->ensureExtensionsRegistered();

		return $this->propertyTypeRegistry;
	}

	public function getPropertyTypeLookup(): PropertyTypeLookup {
		return $this->getPropertyTypeRegistry();
	}

	public function getValueBuilderRegistry(): Neo4jValueBuilderRegistry {
		if ( !isset( $this->valueBuilderRegistry ) ) {
			$this->valueBuilderRegistry = Neo4jValueBuilderRegistry::withCoreBuilders();
		}

		return $this->valueBuilderRegistry;
	}

	public function getRdfValueMapperRegistry(): RdfValueMapperRegistry {
		if ( !isset( $this->rdfValueMapperRegistry ) ) {
			$this->rdfValueMapperRegistry = RdfValueMapperRegistry::withCoreMappers();
		}

		$this->ensureExtensionsRegistered();

		return $this->rdfValueMapperRegistry;
	}

	private function ensureExtensionsRegistered(): void {
		if ( $this->extensionsRegistered ) {
			return;
		}

		$this->extensionsRegistered = true;

		MediaWikiServices::getInstance()->getHookContainer()->run(
			'NeoWikiRegistration',
			[ new NeoWikiRegistrar(
				$this->getPropertyTypeRegistry(),
				$this->getValueBuilderRegistry(),
				$this->getPagePropertyProviderRegistry(),
				$this->getGraphDatabasePluginRegistry(),
				$this->getRdfValueMapperRegistry(),
			) ]
		);
	}

	public function newSubjectContentDataDeserializer(): SubjectContentDataDeserializer {
		return new SubjectContentDataDeserializer( new StatementDeserializer( $this->getPropertyTypeLookup() ) );
	}

	public function getPagePropertyProviderRegistry(): PagePropertyProviderRegistry {
		if ( !isset( $this->pagePropertyProviderRegistry ) ) {
			$this->pagePropertyProviderRegistry = new PagePropertyProviderRegistry();
			$this->pagePropertyProviderRegistry->addProvider( new CorePagePropertyProvider() );
		}

		$this->ensureExtensionsRegistered();

		return $this->pagePropertyProviderRegistry;
	}

	/**
	 * Hook-facing write path (edit/delete/undelete). Each backend is isolated and logged, so a
	 * projection failure never aborts the triggering user operation and one failing backend does not
	 * starve the others. See FailureIsolatingGraphDatabasePlugin.
	 */
	public function getStoreContentUC(): OnRevisionCreatedHandler {
		return $this->newStoreContentHandler( $this->getIsolatingGraphDatabasePlugin() );
	}

	/**
	 * Maintenance rebuild path (RebuildGraphDatabases). Failures propagate so the script reports which
	 * pages failed to reconcile, rather than the hook path's per-plugin isolation swallowing them.
	 */
	private function newRebuildStoreContentHandler(): OnRevisionCreatedHandler {
		return $this->newStoreContentHandler( $this->getGraphDatabasePlugin() );
	}

	private function newStoreContentHandler( GraphDatabasePlugin $graphDatabasePlugin ): OnRevisionCreatedHandler {
		return new OnRevisionCreatedHandler(
			$graphDatabasePlugin,
			$this->getPagePropertiesBuilder(),
		);
	}

	public function getPagePropertiesBuilder(): PagePropertiesBuilder {
		return new PagePropertiesBuilder(
			revisionStore: MediaWikiServices::getInstance()->getRevisionStore(),
			contentHandlerFactory: MediaWikiServices::getInstance()->getContentHandlerFactory(),
			titleFormatter: MediaWikiServices::getInstance()->getTitleFormatter(),
			providerRegistry: $this->getPagePropertyProviderRegistry(),
		);
	}

	public function getRdfNamespaces(): RdfNamespaces {
		return new RdfNamespaces( $this->config->rdfBaseUri );
	}

	public function getRdfSerializer(): RdfSerializer {
		return new HardfRdfSerializer( $this->getRdfNamespaces()->prefixMap() );
	}

	public function newRdfPageProjector(): RdfPageProjector {
		return new RdfPageProjector(
			$this->getRdfValueMapperRegistry(),
			$this->getRdfNamespaces(),
			$this->getSchemaLookup(),
			LoggerFactory::getInstance( 'NeoWiki' ),
		);
	}

	public function newRdfPageLoader(): RdfPageLoader {
		return new RdfPageLoader(
			MediaWikiServices::getInstance()->getWikiPageFactory(),
			$this->getPagePropertiesBuilder(),
		);
	}

	public function newRdfPageExporterForProjection( RdfProjection $projection ): RdfPageExporter {
		return new RdfPageExporter(
			$this->newRdfPageLoader(),
			$projection->projector,
			$projection->serializer,
		);
	}

	/**
	 * Resolves a projection name to its projector and serializer, or null when the name is neither
	 * "native" nor a target that any Mapping page declares. This is the seam the RDF export surfaces
	 * use to select a projection, and that the SPARQL store plugin (#586) consumes for its own store
	 * (it needs only {@see RdfProjection::$projector}). Ontology mappings hold only the target
	 * vocabulary; Subject IRIs stay native, so the native prefixes always seed the serializer.
	 */
	public function newRdfProjection( string $projectionName ): ?RdfProjection {
		if ( $projectionName === self::PROJECTION_NATIVE ) {
			return new RdfProjection( $this->newRdfPageProjector(), $this->getRdfSerializer() );
		}

		$mappings = ( new Mappings( $this->getMappingLookup()->getAllMappings() ) )->forTarget( $projectionName );

		if ( $mappings === [] ) {
			return null;
		}

		return new RdfProjection(
			new OntologyMappingProjector(
				$projectionName,
				$mappings,
				$this->getRdfNamespaces(),
				$this->getRdfValueMapperRegistry(),
				LoggerFactory::getInstance( 'NeoWiki' ),
			),
			new HardfRdfSerializer( $this->ontologyPrefixMap( $mappings ) ),
		);
	}

	/**
	 * The known projection names: "native" plus every target any Mapping page declares. Used to reject
	 * an unknown projection with a helpful list of the valid ones.
	 *
	 * @return string[]
	 */
	public function getRdfProjectionNames(): array {
		return array_merge(
			[ self::PROJECTION_NATIVE ],
			( new Mappings( $this->getMappingLookup()->getAllMappings() ) )->targets()
		);
	}

	/**
	 * The native prefixes (Subject IRIs stay native) plus the Mappings' declared ontology prefixes, for
	 * readable output. Unsafe prefix namespaces are dropped defensively so a Mapping can never inject a
	 * `@prefix` declaration into the document, even though save-time validation already rejects them.
	 *
	 * @param \ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping[] $mappings
	 * @return array<string, string>
	 */
	private function ontologyPrefixMap( array $mappings ): array {
		$prefixes = $this->getRdfNamespaces()->prefixMap();

		foreach ( $mappings as $mapping ) {
			foreach ( $mapping->prefixes as $label => $namespace ) {
				if ( CurieExpander::isSafeAbsoluteIri( $namespace ) ) {
					$prefixes[$label] = $namespace;
				}
			}
		}

		return $prefixes;
	}

	public function getMappingLookup(): MappingLookup {
		return new CachingMappingLookup(
			mappingLookup: new WikiPageMappingLookup(
				pageContentFetcher: $this->getPageContentFetcher(),
				authority: $this->getRequestAuthority(),
				mappingDeserializer: $this->getMappingPersistenceDeserializer(),
				mappingNameLookup: $this->getMappingNameLookup(),
			),
			mappingNameLookup: $this->getMappingNameLookup(),
			cache: MediaWikiServices::getInstance()->getMainWANObjectCache(),
			titleFactory: MediaWikiServices::getInstance()->getTitleFactory(),
			authority: $this->getRequestAuthority(),
			connectionProvider: MediaWikiServices::getInstance()->getConnectionProvider(),
		);
	}

	public function getMappingNameLookup(): MappingNameLookup {
		return new DatabaseMappingNameLookup( db: $this->getDbConnection() );
	}

	public function getMappingPersistenceDeserializer(): MappingPersistenceDeserializer {
		return new MappingPersistenceDeserializer();
	}

	public static function newExportPageRdfApi(): ExportPageRdfApi {
		return new ExportPageRdfApi();
	}

	/**
	 * Propagating fan-out over every backend, used by the maintenance rebuild path so failures surface.
	 */
	public function getGraphDatabasePlugin(): GraphDatabasePlugin {
		if ( !isset( $this->graphDatabasePlugin ) ) {
			$this->graphDatabasePlugin = new CompositeGraphDatabasePlugin( ...$this->getGraphDatabasePlugins() );
		}

		return $this->graphDatabasePlugin;
	}

	/**
	 * Hook-path fan-out: each backend individually wrapped so a projection failure is isolated and
	 * logged rather than aborting the triggering user operation.
	 */
	private function getIsolatingGraphDatabasePlugin(): GraphDatabasePlugin {
		if ( !isset( $this->isolatingGraphDatabasePlugin ) ) {
			$logger = LoggerFactory::getInstance( 'NeoWiki' );

			$this->isolatingGraphDatabasePlugin = new CompositeGraphDatabasePlugin(
				...array_map(
					static fn ( GraphDatabasePlugin $plugin ) => new FailureIsolatingGraphDatabasePlugin( $plugin, $logger ),
					$this->getGraphDatabasePlugins()
				)
			);
		}

		return $this->isolatingGraphDatabasePlugin;
	}

	/**
	 * The graph database plugins to fan out to, Neo4j first when a backend is configured.
	 *
	 * Core's Neo4j plugin is seeded directly here, not via the registry. Registering it via the
	 * registry would make getGraphDatabasePluginRegistry() build the Neo4j plugin, whose construction
	 * transitively fires the NeoWikiRegistration hook and re-enters that accessor. Composing core here
	 * keeps the registry extension-only and the plugin order deterministic.
	 *
	 * @return GraphDatabasePlugin[]
	 */
	private function getGraphDatabasePlugins(): array {
		$plugins = $this->getGraphDatabasePluginRegistry()->getPlugins();

		$neo4jPlugin = $this->getNeo4jPlugin();
		if ( $neo4jPlugin !== null ) {
			array_unshift( $plugins, $neo4jPlugin->getGraphDatabasePlugin() );
		}

		return $plugins;
	}

	public function getGraphDatabasePluginRegistry(): GraphDatabasePluginRegistry {
		if ( !isset( $this->graphDatabasePluginRegistry ) ) {
			$this->graphDatabasePluginRegistry = new GraphDatabasePluginRegistry();
		}

		$this->ensureExtensionsRegistered();

		return $this->graphDatabasePluginRegistry;
	}

	public function getNeo4jPlugin(): ?Neo4jPlugin {
		if ( !$this->config->hasNeo4jBackend() ) {
			return null;
		}

		if ( $this->neo4jPlugin === null ) {
			$this->neo4jPlugin = $this->buildNeo4jPlugin( $this->getSchemaLookup() );
		}

		return $this->neo4jPlugin;
	}

	// Guard for surfaces whose registration is already gated on a configured backend, so callers get a
	// non-null plugin without repeating the null handling.
	public function requireNeo4jPlugin(): Neo4jPlugin {
		$plugin = $this->getNeo4jPlugin();
		if ( $plugin === null ) {
			throw new LogicException( 'A configured Neo4j backend is required here.' );
		}

		return $plugin;
	}

	// Test seam: lets tests build a projection store with a custom SchemaLookup.
	// This is a hack; we should have a proper test environment.
	public function newNeo4jProjectionStore( SchemaLookup $schemaLookup ): GraphDatabasePlugin {
		return $this->buildNeo4jPlugin( $schemaLookup )->getGraphDatabasePlugin();
	}

	private function buildNeo4jPlugin( SchemaLookup $schemaLookup ): Neo4jPlugin {
		return new Neo4jPlugin(
			client: $this->getNeo4jClient(),
			readOnlyClient: $this->getReadOnlyNeo4jClient(),
			schemaLookup: $schemaLookup,
			valueBuilderRegistry: $this->getValueBuilderRegistry(),
			logger: LoggerFactory::getInstance( 'NeoWiki' ),
			wikiId: $this->config->wikiId,
		);
	}

	public function getNeo4jClient(): ClientInterface {
		if ( !isset( $this->neo4jClient ) ) {
			$writeUrl = $this->config->neo4jInternalWriteUrl;
			if ( $writeUrl === null ) {
				throw new GraphBackendNotConfiguredException();
			}
			$this->neo4jClient = ClientBuilder::create()
				->withDriver( 'default', $writeUrl )
				->withDefaultDriver( 'default' )
				->build();
		}

		return $this->neo4jClient;
	}

	public function getReadOnlyNeo4jClient(): ClientInterface {
		if ( !isset( $this->readOnlyNeo4jClient ) ) {
			$readUrl = $this->config->neo4jInternalReadUrl;
			if ( $readUrl === null ) {
				throw new GraphBackendNotConfiguredException();
			}
			$this->readOnlyNeo4jClient = ClientBuilder::create()
				->withDriver( 'default', $readUrl )
				->withDefaultDriver( 'default' )
				->build();
		}

		return $this->readOnlyNeo4jClient;
	}

	public function newCypherQueryService(): Neo4jQueryService {
		return $this->requireNeo4jPlugin()->newQueryService();
	}

	public static function newCypherQueryApi(): CypherQueryApi {
		return new CypherQueryApi(
			self::getInstance()->newCypherQueryService()
		);
	}

	public function getWriteQueryEngine(): Neo4jWriteQueryEngine {
		return $this->requireNeo4jPlugin()->getWriteQueryEngine();
	}

	public function isDevelopmentUIEnabled(): bool {
		return $this->config->enableDevelopmentUIs;
	}

	public function shouldAutoRenderMainSubject(): bool {
		// Behavioral config read live from MainConfig (like NeoWikiEnforceValidation),
		// so the admin's LocalSettings value applies per request and tests can override
		// it via overrideConfigValue() without rebuilding the getInstance() singleton.
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'NeoWikiAutoRenderMainSubject' ) === true;
	}

	public function getPageContentFetcher(): PageContentFetcher {
		return new PageContentFetcher(
			MediaWikiServices::getInstance()->getTitleParser(),
			MediaWikiServices::getInstance()->getRevisionLookup()
		);
	}

	public function getPageContentSaver(): PageContentSaver {
		return new PageContentSaver(
			wikiPageFactory: MediaWikiServices::getInstance()->getWikiPageFactory(),
			performer: $this->getRequestAuthority(),
		);
	}

	private function getRequestAuthority(): Authority {
		return RequestContext::getMain()->getAuthority();
	}

	public function newViewHtmlBuilder(): ViewHtmlBuilder {
		return new ViewHtmlBuilder(
			subjectContentRepository: $this->newSubjectContentRepository()
		);
	}

	public function newPageSubjectsLookup(): PageSubjectsLookup {
		return new PageSubjectsLookup( $this->getSubjectRepository() );
	}

	public function newFrontendModuleLoader(): FrontendModuleLoader {
		$debounceMs = MediaWikiServices::getInstance()->getMainConfig()->get( 'NeoWikiValidationDebounceMs' );

		return new FrontendModuleLoader(
			MediaWikiServices::getInstance()->getHookContainer(),
			is_int( $debounceMs ) ? $debounceMs : 300,
		);
	}

	public function newSubjectContentRepository(): SubjectContentRepository {
		return new MediaWikiSubjectContentRepository(
			wikiPageFactory: MediaWikiServices::getInstance()->getWikiPageFactory(),
			authority: RequestContext::getMain()->getUser(),
			pageContentSaver: $this->getPageContentSaver(),
			revisionLookup: MediaWikiServices::getInstance()->getRevisionLookup(),
		);
	}

	public function newSubjectPageRebuilder(): SubjectPageRebuilder {
		return new SubjectPageRebuilder(
			$this->newRebuildStoreContentHandler(),
			MediaWikiServices::getInstance()->getWikiPageFactory()
		);
	}

	public function newCreateSubjectAction( CreateSubjectPresenter $presenter, Authority $authority ): CreateSubjectAction {
		return new CreateSubjectAction(
			presenter: $presenter,
			subjectRepository: $this->getSubjectRepository(),
			idGenerator: $this->getIdGenerator(),
			writeAuthorizer: $this->newSubjectWriteAuthorizer( $authority ),
			statementListBuilder: $this->getStatementListBuilder(),
			schemaLookup: $this->getSchemaLookup(),
			selectStatementResolver: $this->getSelectStatementResolver(),
			proposedSubjectValidator: $this->getProposedSubjectValidator(),
			validationEnforced: $this->isValidationEnforced(),
		);
	}

	public function getSelectStatementResolver(): SelectStatementResolver {
		return new SelectStatementResolver( new SelectValueResolver() );
	}

	public function getSubjectRepository(): SubjectRepository {
		// TODO: re-enable using the same instance. For some reason this causes an isolation issue in the integration tests
		//if ( !isset( $this->subjectRepository ) ) {
			$this->subjectRepository = $this->newSubjectRepository();
		//}

		return $this->subjectRepository;
	}

	public function newSubjectRepository(): MediaWikiSubjectRepository {
		return new MediaWikiSubjectRepository(
			pageIdentifiersLookup: $this->getPageIdentifiersLookup(),
			revisionLookup: MediaWikiServices::getInstance()->getRevisionLookup(),
			pageContentSaver: $this->getPageContentSaver(),
		);
	}

	private function getIdGenerator(): IdGenerator {
		return new ProductionIdGenerator();
	}

	public function getStatementListBuilder(): StatementListBuilder {
		return new StatementListBuilder(
			propertyTypeLookup: $this->getPropertyTypeLookup(),
			idGenerator: $this->getIdGenerator()
		);
	}

	// NeoWiki requires a configured graph backend: the subject -> page reverse index lives only in Neo4j,
	// so this lookup (and Subject CRUD, {{#view}}, {{#neowiki_value}}, the mw.neowiki.* getters) needs one.
	// A no-backend wiki is a misconfiguration, surfaced loudly rather than silently degraded:
	// getReadOnlyNeo4jClient() throws GraphBackendNotConfiguredException, and the content-page render path
	// (NeoWikiHooks::handleContentPage) short-circuits with a warning instead of failing the page. Making
	// these work without a graph backend needs a MediaWiki-native reverse index; that is future work
	// (#586 / #895), only worthwhile if a deliberate storage-only product is chosen (ADR 019 defers it).
	private function getPageIdentifiersLookup(): PageIdentifiersLookup {
		return new Neo4jPageIdentifiersLookup( $this->getReadOnlyNeo4jClient() );
	}

	public function newDeleteSubjectAction( Authority $authority ): DeleteSubjectAction {
		return new DeleteSubjectAction(
			subjectRepository: $this->getSubjectRepository(),
			writeAuthorizer: $this->newSubjectWriteAuthorizer( $authority ),
			pageIdentifiersLookup: $this->getPageIdentifiersLookup()
		);
	}

	public function newSetMainSubjectAction( SetMainSubjectPresenter $presenter, Authority $authority ): SetMainSubjectAction {
		return new SetMainSubjectAction(
			presenter: $presenter,
			subjectRepository: $this->getSubjectRepository(),
			writeAuthorizer: $this->newSubjectWriteAuthorizer( $authority ),
		);
	}

	public function newSetSubjectsOrderingAction( SetSubjectsOrderingPresenter $presenter, Authority $authority ): SetSubjectsOrderingAction {
		return new SetSubjectsOrderingAction(
			presenter: $presenter,
			subjectRepository: $this->getSubjectRepository(),
			writeAuthorizer: $this->newSubjectWriteAuthorizer( $authority ),
		);
	}

	public function newSubjectPermissionHints( Authority $authority ): SubjectPermissionHints {
		return $this->newAuthorityBasedSubjectAuthorizer( $authority );
	}

	public function newSubjectWriteAuthorizer( Authority $authority ): SubjectWriteAuthorizer {
		return $this->newAuthorityBasedSubjectAuthorizer( $authority );
	}

	private function newAuthorityBasedSubjectAuthorizer( Authority $authority ): AuthorityBasedSubjectAuthorizer {
		return new AuthorityBasedSubjectAuthorizer(
			authority: $authority,
			titleFactory: MediaWikiServices::getInstance()->getTitleFactory()
		);
	}

	public function newGetSchemaQuery( GetSchemaPresenter $presenter ): GetSchemaQuery {
		return new GetSchemaQuery(
			presenter: $presenter,
			schemaLookup: $this->getSchemaLookup(),
			serializer: $this->getSchemaPresentationSerializer()
		);
	}

	public function newGetLayoutQuery( GetLayoutPresenter $presenter ): GetLayoutQuery {
		return new GetLayoutQuery(
			presenter: $presenter,
			layoutLookup: $this->getLayoutLookup(),
			serializer: new LayoutPresentationSerializer(),
		);
	}

	public function getSchemaLookup(): SchemaLookup {
		return new CachingSchemaLookup(
			schemaLookup: new WikiPageSchemaLookup(
				pageContentFetcher: $this->getPageContentFetcher(),
				authority: $this->getRequestAuthority(),
				schemaDeserializer: $this->getPersistenceSchemaDeserializer()
			),
			cache: MediaWikiServices::getInstance()->getMainWANObjectCache(),
			titleFactory: MediaWikiServices::getInstance()->getTitleFactory(),
			authority: $this->getRequestAuthority(),
			connectionProvider: MediaWikiServices::getInstance()->getConnectionProvider()
		);
	}

	public function getSchemaPresentationSerializer(): SchemaPresentationSerializer {
		return new SchemaPresentationSerializer();
	}

	private function getPersistenceSchemaDeserializer(): SchemaPersistenceDeserializer {
		return new SchemaPersistenceDeserializer(
			propertyTypeLookup: $this->getPropertyTypeLookup(),
		);
	}

	public function getLayoutLookup(): LayoutLookup {
		return new WikiPageLayoutLookup(
			pageContentFetcher: $this->getPageContentFetcher(),
			authority: $this->getRequestAuthority(),
			layoutDeserializer: $this->getLayoutPersistenceDeserializer()
		);
	}

	private function getLayoutPersistenceDeserializer(): LayoutPersistenceDeserializer {
		return new LayoutPersistenceDeserializer();
	}

	public function getSchemaNameLookup(): SchemaNameLookup {
		return new DatabaseSchemaNameLookup(
			db: $this->getDbConnection(),
			searchEngine: MediaWikiServices::getInstance()->newSearchEngine()
		);
	}

	public function getSubjectLabelLookup(): SubjectLabelLookup {
		if ( $this->getNeo4jPlugin() === null ) {
			return new NullSubjectLabelLookup();
		}

		return new Neo4jSubjectLabelLookup(
			client: $this->getReadOnlyNeo4jClient()
		);
	}

	public function getDbConnection(): IDatabase {
		$db = MediaWikiServices::getInstance()
			->getDBLoadBalancerFactory()
			->getMainLB()
			->getConnection( (int)DB_PRIMARY );

		if ( !$db ) {
			throw new Exception( 'No connection to the database' );
		}

		return $db;
	}

	public function newGetPageSubjectsQuery( GetPageSubjectsPresenter $presenter ): GetPageSubjectsQuery {
		return new GetPageSubjectsQuery(
			presenter: $presenter,
			subjectRepository: $this->getSubjectRepository(),
			subjectLookup: $this->getSubjectRepository(),
			schemaLookup: $this->getSchemaLookup(),
			schemaSerializer: $this->getSchemaPresentationSerializer(),
			pageIdentifiersLookup: $this->getPageIdentifiersLookup(),
		);
	}

	public function newGetSubjectQuery( RestGetSubjectPresenter $presenter ): GetSubjectQuery {
		return new GetSubjectQuery(
			presenter: $presenter,
			subjectLookup: $this->getSubjectRepository(),
			pageIdentifiersLookup: $this->getPageIdentifiersLookup(),
		);
	}

	public function newGetSubjectQueryForRevision( RestGetSubjectPresenter $presenter, RevisionRecord $revision ): GetSubjectQuery {
		return new GetSubjectQuery(
			presenter: $presenter,
			subjectLookup: new PointInTimeSubjectLookup(
				revisionLookup: MediaWikiServices::getInstance()->getRevisionLookup(),
				pageIdentifiersLookup: $this->getPageIdentifiersLookup(),
				connectionProvider: MediaWikiServices::getInstance()->getConnectionProvider(),
				primaryRevision: $revision,
			),
			pageIdentifiersLookup: $this->getPageIdentifiersLookup(),
		);
	}

	public function newReplaceSubjectAction( ReplaceSubjectPresenter $presenter, Authority $authority ): ReplaceSubjectAction {
		return new ReplaceSubjectAction(
			subjectRepository: $this->getSubjectRepository(),
			writeAuthorizer: $this->newSubjectWriteAuthorizer( $authority ),
			statementListBuilder: $this->getStatementListBuilder(),
			schemaLookup: $this->getSchemaLookup(),
			selectStatementResolver: $this->getSelectStatementResolver(),
			proposedSubjectValidator: $this->getProposedSubjectValidator(),
			presenter: $presenter,
			validationEnforced: $this->isValidationEnforced(),
			pageIdentifiersLookup: $this->getPageIdentifiersLookup(),
		);
	}

	private function isValidationEnforced(): bool {
		// Behavioral config is read live from MainConfig (like Neo4jQueryLimits), so the admin's
		// LocalSettings value applies per request and tests can override it via overrideConfigValue()
		// without rebuilding the getInstance() singleton (which bakes only bootstrap config).
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'NeoWikiEnforceValidation' ) === true;
	}

	public function getSubjectValidator(): SubjectValidator {
		return new SubjectValidator(
			propertyTypeLookup: $this->getPropertyTypeLookup(),
		);
	}

	public function getProposedSubjectValidator(): ProposedSubjectValidator {
		return new ProposedSubjectValidator(
			schemaLookup: $this->getSchemaLookup(),
			subjectValidator: $this->getSubjectValidator(),
		);
	}

	public function newValidateSubjectQuery(): ValidateSubjectQuery {
		return new ValidateSubjectQuery(
			schemaLookup: $this->getSchemaLookup(),
			subjectValidator: $this->getSubjectValidator(),
			statementListBuilder: $this->getStatementListBuilder(),
			selectStatementResolver: $this->getSelectStatementResolver(),
		);
	}

	public function newValidateSubjectUpdateQuery(): ValidateSubjectUpdateQuery {
		return new ValidateSubjectUpdateQuery(
			subjectRepository: $this->getSubjectRepository(),
			schemaLookup: $this->getSchemaLookup(),
			subjectValidator: $this->getSubjectValidator(),
			statementListBuilder: $this->getStatementListBuilder(),
			selectStatementResolver: $this->getSelectStatementResolver(),
		);
	}

	public static function newValidateSubjectApi(): ValidateSubjectApi {
		return new ValidateSubjectApi(
			query: self::getInstance()->newValidateSubjectQuery(),
		);
	}

	public static function newValidateSubjectUpdateApi(): ValidateSubjectUpdateApi {
		return new ValidateSubjectUpdateApi(
			query: self::getInstance()->newValidateSubjectUpdateQuery(),
		);
	}

	public static function newCreateMainSubjectApi(): CreateSubjectApi {
		return new CreateSubjectApi(
			isMainSubject: true,
			csrfValidator: self::getCsrfValidator()
		);
	}

	public static function newCreateChildSubjectApi(): CreateSubjectApi {
		return new CreateSubjectApi(
			isMainSubject: false,
			csrfValidator: self::getCsrfValidator()
		);
	}

	public static function newGetSubjectApi(): GetSubjectApi|Response {
		return new GetSubjectApi();
	}

	public static function newGetPageSubjectsApi(): GetPageSubjectsApi {
		return new GetPageSubjectsApi();
	}

	public static function newReplaceSubjectApi(): ReplaceSubjectApi {
		return new ReplaceSubjectApi( csrfValidator: self::getCsrfValidator() );
	}

	public static function newDeleteSubjectApi(): DeleteSubjectApi {
		return new DeleteSubjectApi( csrfValidator: self::getCsrfValidator() );
	}

	public static function newSetMainSubjectApi(): SetMainSubjectApi {
		return new SetMainSubjectApi( csrfValidator: self::getCsrfValidator() );
	}

	public static function newSetSubjectsOrderingApi(): SetSubjectsOrderingApi {
		return new SetSubjectsOrderingApi( csrfValidator: self::getCsrfValidator() );
	}

	public static function newGetSchemaApi(): GetSchemaApi {
		return new GetSchemaApi();
	}

	public static function newGetLayoutApi(): GetLayoutApi {
		return new GetLayoutApi();
	}

	public static function newGetSchemaNamesApi(): GetSchemaNamesApi {
		return new GetSchemaNamesApi();
	}

	public static function newGetSchemaSummariesApi(): GetSchemaSummariesApi {
		return new GetSchemaSummariesApi();
	}

	public function getLayoutNameLookup(): LayoutNameLookup {
		return new DatabaseLayoutNameLookup(
			db: $this->getDbConnection(),
		);
	}

	public static function newGetLayoutSummariesApi(): GetLayoutSummariesApi {
		return new GetLayoutSummariesApi();
	}

	public static function newGetSubjectLabelsApi(): GetSubjectLabelsApi {
		return new GetSubjectLabelsApi();
	}

	private static function getCsrfValidator(): CsrfValidator {
		$request = ( \RequestContext::getMain() )->getRequest();
		return new CsrfValidator(
			$request,
			new CsrfTokenSet( $request )
		);
	}

	public function getNeoWikiRootDirectory(): string {
		return __DIR__ . '/..';
	}

}
