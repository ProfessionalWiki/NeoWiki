<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use Exception;
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
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\CompositeCypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer;
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
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePluginRegistry;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Application\LayoutLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
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
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageLayoutLookup;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\ExplainCypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\KeywordCypherQueryValidator;
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

	private PropertyTypeRegistry $propertyTypeRegistry;
	private PagePropertyProviderRegistry $pagePropertyProviderRegistry;
	private Neo4jValueBuilderRegistry $valueBuilderRegistry;
	private bool $extensionsRegistered = false;
	private SubjectRepository $subjectRepository;
	private CompositeGraphDatabasePlugin $graphDatabasePlugin;
	private GraphDatabasePluginRegistry $graphDatabasePluginRegistry;
	private ?Neo4jPlugin $neo4jPlugin = null;
	private ClientInterface $neo4jClient;
	private ClientInterface $readOnlyNeo4jClient;

	public static function getInstance(): self {
		/** @var ?self $instance */
		static $instance = null;

		$instance ??= new self(
			( new NeoWikiConfigFactory() )->buildFromMediaWikiConfig( MediaWikiServices::getInstance()->getMainConfig() )
		);

		return $instance;
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

	public function getStoreContentUC(): OnRevisionCreatedHandler {
		return new OnRevisionCreatedHandler(
			$this->getGraphDatabasePlugin(),
			new PagePropertiesBuilder(
				revisionStore: MediaWikiServices::getInstance()->getRevisionStore(),
				contentHandlerFactory: MediaWikiServices::getInstance()->getContentHandlerFactory(),
				titleFormatter: MediaWikiServices::getInstance()->getTitleFormatter(),
				providerRegistry: $this->getPagePropertyProviderRegistry(),
			)
		);
	}

	public function getGraphDatabasePlugin(): GraphDatabasePlugin {
		if ( !isset( $this->graphDatabasePlugin ) ) {
			// Seed core's Neo4j plugin directly into the composite, not the registry. Registering it via
			// the registry would make getGraphDatabasePluginRegistry() build the Neo4j plugin, whose
			// construction transitively fires the NeoWikiRegistration hook and re-enters that accessor.
			// Composing core here keeps the registry extension-only and the plugin order deterministic.
			$this->graphDatabasePlugin = new CompositeGraphDatabasePlugin(
				$this->getNeo4jPlugin()->getGraphDatabasePlugin(),
				...$this->getGraphDatabasePluginRegistry()->getPlugins()
			);
		}

		return $this->graphDatabasePlugin;
	}

	public function getGraphDatabasePluginRegistry(): GraphDatabasePluginRegistry {
		if ( !isset( $this->graphDatabasePluginRegistry ) ) {
			$this->graphDatabasePluginRegistry = new GraphDatabasePluginRegistry();
		}

		$this->ensureExtensionsRegistered();

		return $this->graphDatabasePluginRegistry;
	}

	public function getNeo4jPlugin(): Neo4jPlugin {
		if ( $this->neo4jPlugin === null ) {
			$this->neo4jPlugin = $this->buildNeo4jPlugin( $this->getSchemaLookup() );
		}

		return $this->neo4jPlugin;
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
			$this->neo4jClient = ClientBuilder::create()
				->withDriver( 'default', $this->config->neo4jInternalWriteUrl )
				->withDefaultDriver( 'default' )
				->build();
		}

		return $this->neo4jClient;
	}

	public function getReadOnlyNeo4jClient(): ClientInterface {
		if ( !isset( $this->readOnlyNeo4jClient ) ) {
			$this->readOnlyNeo4jClient = ClientBuilder::create()
				->withDriver( 'default', $this->config->neo4jInternalReadUrl )
				->withDefaultDriver( 'default' )
				->build();
		}

		return $this->readOnlyNeo4jClient;
	}

	public function getCypherQueryValidator(): CypherQueryValidator {
		return new CompositeCypherQueryValidator( [
			new KeywordCypherQueryValidator(),
			new ExplainCypherQueryValidator( $this->getReadOnlyNeo4jClient() ),
		] );
	}

	public function newCypherQueryService(): Neo4jQueryService {
		return new Neo4jQueryService(
			$this->getNeo4jPlugin()->getReadQueryEngine(),
			$this->getCypherQueryValidator(),
			new Neo4jResultNormalizer(),
		);
	}

	public static function newCypherQueryApi(): CypherQueryApi {
		return new CypherQueryApi(
			self::getInstance()->newCypherQueryService()
		);
	}

	public function getWriteQueryEngine(): Neo4jWriteQueryEngine {
		return $this->getNeo4jPlugin()->getWriteQueryEngine();
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
			$this->getStoreContentUC(),
			MediaWikiServices::getInstance()->getWikiPageFactory()
		);
	}

	public function newCreateSubjectAction( CreateSubjectPresenter $presenter, Authority $authority ): CreateSubjectAction {
		return new CreateSubjectAction(
			presenter: $presenter,
			subjectRepository: $this->getSubjectRepository(),
			idGenerator: $this->getIdGenerator(),
			subjectAuthorizer: $this->newSubjectAuthorizer( $authority ),
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

	private function getPageIdentifiersLookup(): PageIdentifiersLookup {
		return new Neo4jPageIdentifiersLookup( $this->getReadOnlyNeo4jClient() );
	}

	public function newDeleteSubjectAction( Authority $authority ): DeleteSubjectAction {
		return new DeleteSubjectAction(
			subjectRepository: $this->getSubjectRepository(),
			subjectAuthorizer: $this->newSubjectAuthorizer( $authority ),
			pageIdentifiersLookup: $this->getPageIdentifiersLookup()
		);
	}

	public function newSetMainSubjectAction( SetMainSubjectPresenter $presenter, Authority $authority ): SetMainSubjectAction {
		return new SetMainSubjectAction(
			presenter: $presenter,
			subjectRepository: $this->getSubjectRepository(),
			subjectAuthorizer: $this->newSubjectAuthorizer( $authority ),
		);
	}

	public function newSetSubjectsOrderingAction( SetSubjectsOrderingPresenter $presenter, Authority $authority ): SetSubjectsOrderingAction {
		return new SetSubjectsOrderingAction(
			presenter: $presenter,
			subjectRepository: $this->getSubjectRepository(),
			subjectAuthorizer: $this->newSubjectAuthorizer( $authority ),
		);
	}

	public function newSubjectAuthorizer( Authority $authority ): SubjectAuthorizer {
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
			subjectAuthorizer: $this->newSubjectAuthorizer( $authority ),
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
