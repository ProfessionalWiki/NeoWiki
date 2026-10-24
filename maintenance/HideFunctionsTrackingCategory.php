<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\EntryPoints\FunctionsTrackingCategory;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * update.php runs this once, so a category page deleted afterwards stays deleted.
 */
class HideFunctionsTrackingCategory extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Creates the page of the tracking category that lists the pages calling NeoWiki functions, as a ' .
			'hidden category, so the category is not shown on those pages. Leaves an existing category page as it is.'
		);
	}

	protected function getUpdateKey(): string {
		return 'neowiki-hide-functions-tracking-category';
	}

	protected function doDBUpdates(): bool {
		$allCreated = true;

		foreach ( $this->categories() as $category ) {
			$allCreated = $this->createHiddenUnlessItExists( $category ) && $allCreated;
		}

		return $allCreated;
	}

	/**
	 * More than one when the category name varies by namespace, none when the tracking category is disabled.
	 *
	 * @return Title[]
	 */
	private function categories(): array {
		$services = MediaWikiServices::getInstance();
		$trackingCategories = $services->getTrackingCategories()->getTrackingCategories();

		return array_map(
			static fn( LinkTarget $category ): Title => $services->getTitleFactory()->newFromLinkTarget( $category ),
			$trackingCategories[FunctionsTrackingCategory::MESSAGE_KEY]['cats']
		);
	}

	private function createHiddenUnlessItExists( Title $category ): bool {
		if ( $category->exists() ) {
			$this->output( "{$category->getPrefixedText()} already exists; left as it is.\n" );
			return true;
		}

		$status = $this->newPageContentSaver()->createPage(
			$category,
			[ SlotRecord::MAIN => new WikitextContent( '__HIDDENCAT__' ) ],
			CommentStoreComment::newUnsavedComment( '' )
		);

		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->error( "Could not create {$category->getPrefixedText()}: {$status->errorMessage}" );
			return false;
		}

		$this->output( "Created {$category->getPrefixedText()} as a hidden category.\n" );
		return true;
	}

	/**
	 * Not as the NeoWiki user: the demo data import deletes the pages that user created which the demo data lacks.
	 */
	private function newPageContentSaver(): PageContentSaver {
		return new PageContentSaver(
			wikiPageFactory: MediaWikiServices::getInstance()->getWikiPageFactory(),
			performer: User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] ),
		);
	}

}

$maintClass = HideFunctionsTrackingCategory::class;
require_once RUN_MAINTENANCE_IF_MAIN;
