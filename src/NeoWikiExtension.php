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
use MediaWiki\Session\CsrfTokenSet;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\PatchSubject\PatchSubjectAction;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaQuery;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionIdGenerator;
use ProfessionalWiki\NeoWiki\Persistence\QueryStore;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\WriteQueryEngine;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeToValueType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSchemaNamesApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectLabelsApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\PatchSubjectApi;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentFetcher;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaLookup;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\SubjectUpdaterFactory;
use ProfessionalWiki\NeoWiki\Persistence\SchemaNameLookup;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Presentation\FactBox;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Presentation\TwigEnvironmentFactory;
use ProfessionalWiki\NeoWiki\Presentation\TwigTemplateRenderer;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
use Wikimedia\Rdbms\IDatabase;

class NeoWikiExtension {

	public const int NS_SCHEMA = 7474;

	private PropertyTypeRegistry $propertyTypeRegistry;
	private SubjectRepository $subjectRepository;
	private Neo4jQueryStore $queryStore;
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

		return $this->propertyTypeRegistry;
	}

	public function getPropertyTypeLookup(): PropertyTypeLookup {
		return $this->getPropertyTypeRegistry();
	}

	public function getPropertyTypeToValueType(): PropertyTypeToValueType {
		return new PropertyTypeToValueType( $this->getPropertyTypeRegistry() );
	}

	public function newSubjectContentDataDeserializer(): SubjectContentDataDeserializer {
		return new SubjectContentDataDeserializer( new StatementDeserializer( $this->getPropertyTypeToValueType() ) );
	}

	public function getStoreContentUC(): OnRevisionCreatedHandler {
		return new OnRevisionCreatedHandler(
			$this->getQueryStore(),
			new PagePropertiesBuilder(
				revisionStore: MediaWikiServices::getInstance()->getRevisionStore(),
				contentHandlerFactory: MediaWikiServices::getInstance()->getContentHandlerFactory()
			)
		);
	}

	public function getQueryStore(): QueryStore {
		if ( !isset( $this->queryStore ) ) {
			$this->queryStore = $this->newNeo4jQueryStore( $this->getSchemaLookup() );
		}

		return $this->queryStore;
	}

	public function newNeo4jQueryStore( SchemaLookup $schemaLookup ): Neo4jQueryStore {
		return new Neo4jQueryStore(
			client: $this->getNeo4jClient(),
			readOnlyClient: $this->getReadOnlyNeo4jClient(),
			subjectUpdaterFactory: new SubjectUpdaterFactory(
				schemaLookup: $schemaLookup, // Note: this is a hack, we should have a proper test environment
				propertyTypeLookup: $this->getPropertyTypeLookup(),
				logger: LoggerFactory::getInstance( 'NeoWiki' )
			),
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

	public function getWriteQueryEngine(): WriteQueryEngine {
		$this->getQueryStore();
		return $this->queryStore;
	}

	public function isDevelopmentUIEnabled(): bool {
		return $this->config->enableDevelopmentUIs;
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

	public function getFactBox(): FactBox {
		return new FactBox(
			templateRenderer: $this->newTwigTemplateRenderer(),
			subjectContentRepository: $this->newSubjectContentRepository()
		);
	}

	private function newTwigTemplateRenderer(): TwigTemplateRenderer {
		return new TwigTemplateRenderer(
			TwigEnvironmentFactory::create( __DIR__ . '/../templates' ),
			LoggerFactory::getInstance( 'NeoWiki' )
		);
	}

	public function newSubjectContentRepository(): SubjectContentRepository {
		return new SubjectContentRepository(
			wikiPageFactory: MediaWikiServices::getInstance()->getWikiPageFactory(),
			authority: RequestContext::getMain()->getUser(),
			pageContentSaver: $this->getPageContentSaver(),
		);
	}

	public function newCreateSubjectAction( CreateSubjectPresenter $presenter, Authority $authority ): CreateSubjectAction {
		return new CreateSubjectAction(
			presenter: $presenter,
			subjectRepository: $this->getSubjectRepository(),
			guidGenerator: $this->getIdGenerator(),
			subjectAuthorizer: $this->newSubjectAuthorizer( $authority ),
			statementListPatcher: $this->getStatementListPatcher()
		);
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

	public function getStatementListPatcher(): StatementListPatcher {
		return new StatementListPatcher(
			propertyTypeToValueType: $this->getPropertyTypeToValueType(),
			idGenerator: $this->getIdGenerator()
		);
	}

	private function getPageIdentifiersLookup(): PageIdentifiersLookup {
		return new Neo4jPageIdentifiersLookup( $this->getReadOnlyNeo4jClient() );
	}

	public function newDeleteSubjectAction( Authority $authority ): DeleteSubjectAction {
		return new DeleteSubjectAction(
			subjectRepository: $this->getSubjectRepository(),
			subjectAuthorizer: $this->newSubjectAuthorizer( $authority )
		);
	}

	public function newSubjectAuthorizer( Authority $authority ): SubjectAuthorizer {
		return new AuthorityBasedSubjectAuthorizer(
			authority: $authority
		);
	}

	public function newGetSchemaQuery( GetSchemaPresenter $presenter ): GetSchemaQuery {
		return new GetSchemaQuery(
			presenter: $presenter,
			schemaLookup: $this->getSchemaLookup(),
			serializer: $this->getSchemaPresentationSerializer()
		);
	}

	public function getSchemaLookup(): SchemaLookup {
		return new WikiPageSchemaLookup(
			pageContentFetcher: $this->getPageContentFetcher(),
			authority: $this->getRequestAuthority(),
			schemaDeserializer: $this->getPersistenceSchemaDeserializer()
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

	public function newGetSubjectQuery( RestGetSubjectPresenter $presenter ): GetSubjectQuery {
		return new GetSubjectQuery(
			presenter: $presenter,
			subjectLookup: $this->getSubjectRepository(),
			pageIdentifiersLookup: $this->getPageIdentifiersLookup(),
		);
	}

	public function newPatchSubjectAction( Authority $authority ): PatchSubjectAction {
		return new PatchSubjectAction(
			subjectRepository: $this->getSubjectRepository(),
			subjectAuthorizer: $this->newSubjectAuthorizer( $authority ),
			patcher: $this->getStatementListPatcher()
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

	public static function newPatchSubjectApi(): PatchSubjectApi {
		return new PatchSubjectApi( csrfValidator: self::getCsrfValidator() );
	}

	public static function newDeleteSubjectApi(): DeleteSubjectApi {
		return new DeleteSubjectApi( csrfValidator: self::getCsrfValidator() );
	}

	public static function newGetSchemaApi(): GetSchemaApi {
		return new GetSchemaApi();
	}

	public static function newGetSchemaNamesApi(): GetSchemaNamesApi {
		return new GetSchemaNamesApi();
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
