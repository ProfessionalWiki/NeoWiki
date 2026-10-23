<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use FileFetcher\SimpleFileFetcher;
use Maintenance;
use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPagesAction;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\ImportPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\MappingContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\PageContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SchemaContentSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\SubjectPageSource;
use ProfessionalWiki\NeoWiki\Application\Actions\ImportPages\LayoutContentSource;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\FirstRevisionAuthorPageTitlesLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiPageDeleter;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use User;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class ImportDemoData extends Maintenance implements ImportPresenter {

	// The demo Mapping page whose projection the EdmSparqlPage examples query: DemoData/Mapping/EDM.json.
	private const string EDM_PROJECTION = 'EDM';

	/** @var string[] */
	private array $failures = [];

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription( 'Creates NeoWiki demo data, including schemas and subjects' );
	}

	/**
	 * Fails by returning false rather than through fatalError(), which would exit before the runner's
	 * final commit and deferred updates.
	 */
	public function execute(): bool {
		$this->newImportAction()->import();

		if ( $this->failures === [] ) {
			return true;
		}

		$this->error( "The import is incomplete. These pages failed:\n" . implode( "\n", $this->failures ) );

		return false;
	}

	private function newImportAction(): ImportPagesAction {
		$user = $this->getUser();
		$services = MediaWikiServices::getInstance();

		return new ImportPagesAction(
			presenter: $this,
			pageContentSaver: new PageContentSaver(
				wikiPageFactory: $services->getWikiPageFactory(),
				performer: $user,
			),
			importedPageTitlesLookup: new FirstRevisionAuthorPageTitlesLookup(
				db: $this->getPrimaryDB(),
				actorNormalization: $services->getActorNormalization(),
				revisionLookup: $services->getRevisionLookup(),
				titleFactory: $services->getTitleFactory(),
				importer: $user,
			),
			pageDeleter: new MediaWikiPageDeleter(
				deletePageFactory: $services->getDeletePageFactory(),
				performer: $user,
			),
			schemaContentSource: new SchemaContentSource(
				NeoWikiExtension::getInstance()->getNeoWikiRootDirectory() . '/DemoData/Schema',
				new SimpleFileFetcher()
			),
			subjectPageSource: new SubjectPageSource(
				NeoWikiExtension::getInstance()->getNeoWikiRootDirectory() . '/DemoData/Subject',
				new SimpleFileFetcher()
			),
			pageContentSource: new PageContentSource(
				$this->getPageDirectories(),
				new SimpleFileFetcher()
			),
			moduleContentSource: new PageContentSource(
				[
					NeoWikiExtension::getInstance()->getNeoWikiRootDirectory() . '/DemoData/Module',
				],
				new SimpleFileFetcher()
			),
			mediaWikiContentSource: new PageContentSource(
				[
					NeoWikiExtension::getInstance()->getNeoWikiRootDirectory() . '/DemoData/MediaWiki',
				],
				new SimpleFileFetcher()
			),
			layoutContentSource: new LayoutContentSource(
				NeoWikiExtension::getInstance()->getNeoWikiRootDirectory() . '/DemoData/Layout',
				new SimpleFileFetcher()
			),
			mappingContentSource: new MappingContentSource(
				NeoWikiExtension::getInstance()->getNeoWikiRootDirectory() . '/DemoData/Mapping',
				new SimpleFileFetcher()
			),
			graphStoreProjections: array_filter( NeoWikiExtension::getInstance()->getMappingDefinedStoreProjections() ),
		);
	}

	/**
	 * Both SPARQL directories hold pages whose examples only work on a wiki whose queried store can
	 * answer them, so each is gated on the projections that store holds rather than always imported.
	 * `SparqlPage` asks in the native vocabulary; `EdmSparqlPage` asks in EDM and joins the two, so it
	 * needs both. Importing either without its projections would render examples that silently return
	 * no rows, and claim projections the store does not hold.
	 *
	 * @return string[]
	 */
	private function getPageDirectories(): array {
		$config = NeoWikiExtension::getInstance()->config;
		$rootDirectory = NeoWikiExtension::getInstance()->getNeoWikiRootDirectory();

		$directories = [ $rootDirectory . '/DemoData/Page' ];

		$holdsNative = $config->queriedStoreHoldsProjection( RdfPageProjector::PROJECTION );

		if ( $holdsNative ) {
			$directories[] = $rootDirectory . '/DemoData/SparqlPage';
		}

		if ( $holdsNative && $config->queriedStoreHoldsProjection( self::EDM_PROJECTION ) ) {
			$directories[] = $rootDirectory . '/DemoData/EdmSparqlPage';
		}

		return $directories;
	}

	private function getUser(): User {
		return User::newSystemUser( 'NeoWiki', [ 'steal' => true ] );
	}

	public function presentDone(): void {
		$this->outputChanneled( 'Import finished' );
	}

	public function presentImportStarted( string $pageTitle ): void {
		$this->outputChanneled( "Importing $pageTitle... ", $pageTitle );
	}

	public function presentCreatedRevision( string $pageTitle ): void {
		$this->outputChanneled( "done", $pageTitle );
	}

	public function presentNoChanges( string $pageTitle ): void {
		$this->outputChanneled( "done (no changes)", $pageTitle );
	}

	public function presentImportFailed( string $pageTitle, string $errorMessage ): void {
		$this->presentFailure( $pageTitle, $errorMessage );
	}

	public function presentDeletionStarted( string $pageTitle ): void {
		$this->outputChanneled( "Deleting $pageTitle... ", $pageTitle );
	}

	public function presentDeleted( string $pageTitle ): void {
		$this->outputChanneled( "done", $pageTitle );
	}

	public function presentDeletionFailed( string $pageTitle, string $errorMessage ): void {
		$this->presentFailure( $pageTitle, $errorMessage );
	}

	private function presentFailure( string $pageTitle, string $errorMessage ): void {
		$this->outputChanneled( "FAILED: $errorMessage", $pageTitle );
		$this->failures[] = "$pageTitle: $errorMessage";
	}

}

$maintClass = ImportDemoData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
