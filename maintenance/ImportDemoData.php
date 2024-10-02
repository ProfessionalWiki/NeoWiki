<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use FileFetcher\SimpleFileFetcher;
use Maintenance;
use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\ImportPagesAction;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\ImportPresenter;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\PageContentSource;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\SchemaContentSource;
use ProfessionalWiki\NeoWiki\MediaWiki\Application\Actions\ImportPages\SubjectPageSource;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\PageContentSaver;
use User;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class ImportDemoData extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription( 'Creates NeoWiki demo data, including schemas and subjects' );
	}

	public function execute(): void {
		$this->newImportAction()->import();
	}

	private function newImportAction(): ImportPagesAction {
		$user = $this->getUser();

		return new ImportPagesAction(
			presenter: $this->newImportPresenter(),
			pageContentSaver: new PageContentSaver(
				wikiPageFactory: MediaWikiServices::getInstance()->getWikiPageFactory(),
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
				[
					NeoWikiExtension::getInstance()->getNeoWikiRootDirectory() . '/DemoData/Page',
				],
				new SimpleFileFetcher()
			),
			moduleContentSource: new PageContentSource(
				[
					// NeoWikiExtension::getInstance()->getNeoWikiRootDirectory() . '/DemoData/Module'
				],
				new SimpleFileFetcher()
			)
		);
	}

	private function getUser(): User {
		return User::newSystemUser( 'NeoWiki', [ 'steal' => true ] );
	}

	private function newImportPresenter(): object {
		return new class ( $this ) implements ImportPresenter {

			public function __construct(
				private readonly Maintenance $maintenance
			) {
			}

			public function presentDone(): void {
				$this->maintenance->outputChanneled( 'Import finished' );
			}

			public function presentImportStarted( string $pageTitle ): void {
				$this->maintenance->outputChanneled( "Importing $pageTitle... ", $pageTitle );
			}

			public function presentCreatedRevision( string $pageTitle ): void {
				$this->maintenance->outputChanneled( "done", $pageTitle );
			}

			public function presentNoChanges( string $pageTitle ): void {
				$this->maintenance->outputChanneled( "done (no changes)", $pageTitle );
			}

			public function presentImportFailed( string $pageTitle, string $errorMessage ): void {
				$this->maintenance->outputChanneled( "FAILED: $errorMessage", $pageTitle );
			}

		};
	}

}

$maintClass = ImportDemoData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
