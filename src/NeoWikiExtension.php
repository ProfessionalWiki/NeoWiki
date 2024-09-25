<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki;

use Exception;
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
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\StatementListPatcher;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\FormatTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatLookup;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormatRegistry;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\CreateSubjectApi;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\DeleteSubjectApi;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\GetSchemaApi;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\GetSchemaNamesApi;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\GetSubjectApi;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\REST\PatchSubjectApi;
use ProfessionalWiki\NeoWiki\Infrastructure\GuidGenerator;
use ProfessionalWiki\NeoWiki\Infrastructure\ProductionGuidGenerator;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\OnRevisionCreatedHandler;
use ProfessionalWiki\NeoWiki\MediaWiki\Infrastructure\AuthorityBasedSubjectActionAuthorizer;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\PageContentFetcher;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\WikiPageSchemaLookup;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\SchemaNameLookup;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\FactBox;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\RestGetSubjectPresenter;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\TwigEnvironmentFactory;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\TwigTemplateRenderer;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
use Wikimedia\Rdbms\IDatabase;

class NeoWikiExtension {

	public const int NS_SCHEMA = 7474;

	private ValueFormatRegistry $formatRegistry;
	private SubjectRepository $subjectRepository;

	public static function getInstance(): self {
		/** @var ?NeoWikiExtension $instance */
		static $instance = null;
		$instance ??= new self();
		return $instance;
	}

	public function getFormatRegistry(): ValueFormatRegistry {
		if ( !isset( $this->formatRegistry ) ) {
			$this->formatRegistry = ValueFormatRegistry::withCoreFormats();
		}

		return $this->formatRegistry;
	}

	public function getValueFormatLookup(): ValueFormatLookup {
		return $this->getFormatRegistry();
	}

	public function getFormatTypeLookup(): FormatTypeLookup {
		return new FormatTypeLookup( $this->getFormatRegistry() );
	}

	public function newSubjectContentDataDeserializer(): SubjectContentDataDeserializer {
		return new SubjectContentDataDeserializer( new StatementDeserializer( $this->getFormatTypeLookup() ) );
	}

	public function getStoreContentUC(): OnRevisionCreatedHandler {
		return new OnRevisionCreatedHandler(
			$this->getQueryStore(),
			new PagePropertiesBuilder(
				revisionStore: MediaWikiServices::getInstance()->getRevisionStore()
			)
		);
	}

	public function isDevelopmentUIEnabled(): bool {
		return true; // FIXME
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
			guidGenerator: $this->getGuidGenerator(),
			subjectActionAuthorizer: $this->newSubjectAuthorizer( $authority ),
			statementListPatcher: $this->getStatementListPatcher()
		);
	}

	public function getSubjectRepository(): SubjectRepository {
		if ( !isset( $this->subjectRepository ) ) {
			$this->subjectRepository = $this->newSubjectRepository();
		}

		return $this->subjectRepository;
	}

	public function newSubjectRepository(): MediaWikiSubjectRepository {
		return new MediaWikiSubjectRepository(
			pageIdentifiersLookup: $this->getPageIdentifiersLookup(),
			revisionLookup: MediaWikiServices::getInstance()->getRevisionLookup(),
			pageContentSaver: $this->getPageContentSaver(),
		);
	}

	private function getGuidGenerator(): GuidGenerator {
		return new ProductionGuidGenerator();
	}

	public function getStatementListPatcher(): StatementListPatcher {
		return new StatementListPatcher(
			formatTypeLookup: $this->getFormatTypeLookup(),
			guidGenerator: $this->getGuidGenerator()
		);
	}

	private function getPageIdentifiersLookup(): PageIdentifiersLookup {
		return new Neo4JPageIdentifiersLookup( $this->getReadOnlyNeo4jClient() );
	}

	public function newDeleteSubjectAction( Authority $authority ): DeleteSubjectAction {
		return new DeleteSubjectAction(
			subjectRepository: $this->getSubjectRepository(),
			subjectActionAuthorizer: $this->newSubjectAuthorizer( $authority )
		);
	}

	public function newSubjectAuthorizer( Authority $authority ): SubjectAuthorizer {
		return new AuthorityBasedSubjectActionAuthorizer(
			authority: $authority
		);
	}

	public function newGetSchemaQuery( GetSchemaPresenter $presenter ): GetSchemaQuery {
		return new GetSchemaQuery(
			presenter: $presenter,
			schemaLookup: $this->getSchemaLookup(),
			serializer: $this->getPersistenceSchemaSerializer()
		);
	}

	public function getSchemaLookup(): SchemaLookup {
		return new WikiPageSchemaLookup(
			pageContentFetcher: $this->getPageContentFetcher(),
			authority: $this->getRequestAuthority(),
			schemaDeserializer: $this->getPersistenceSchemaDeserializer()
		);
	}

	public function getPersistenceSchemaSerializer(): SchemaPresentationSerializer {
		return new SchemaPresentationSerializer();
	}

	private function getPersistenceSchemaDeserializer(): SchemaPersistenceDeserializer {
		return new SchemaPersistenceDeserializer(
			formatLookup: $this->getValueFormatLookup(),
		);
	}

	public function getSchemaNameLookup(): SchemaNameLookup {
		return new DatabaseSchemaNameLookup(
			db: $this->getDbConnection(),
			searchEngine: MediaWikiServices::getInstance()->newSearchEngine()
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
			subjectActionAuthorizer: $this->newSubjectAuthorizer( $authority ),
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

	private static function getCsrfValidator(): CsrfValidator {
		$request = ( \RequestContext::getMain() )->getRequest();
		return new CsrfValidator(
			$request,
			new CsrfTokenSet( $request )
		);
	}

}
