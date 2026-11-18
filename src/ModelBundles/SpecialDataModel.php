<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\ModelBundles;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\DocumentationUrl;

class SpecialDataModel extends SpecialPage {

	public function __construct() {
		parent::__construct( 'DataModel' );
	}

	/**
	 * @param ?string $subPage "setup" opens the setup of the data model.
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );
		$this->addHelpLink( DocumentationUrl::ModelBundles->value, true );

		$extension = NeoWikiExtension::getInstance();
		$extension->newFrontendModuleLoader()->load( $this->getOutput(), $this->getSkin() );
		$this->getOutput()->addModules( 'ext.neowiki.modelBundles' );
		$this->getOutput()->addJsConfigVars( [
			'wgNeoWikiModelBundleCatalogs' => $this->getConfig()->get( 'NeoWikiModelBundleCatalogs' ),
			'wgNeoWikiModelBundlePreselection' => $this->getConfig()->get( 'NeoWikiModelBundlePreselection' ),
			'wgNeoWikiModelBundlesSetup' => [
				'wikiHasSchemas' => ModelBundlesNoticeHook::wikiHasSchemas(),
				'wikiHasSubjects' => $extension->getPageIdentifiersLookup()->wikiHasSubjects(),
				'canEditSettings' => $this->canEditSettings( $extension ),
				'settings' => $extension->newConfigEditorJsConfigBuilder( $this->getAuthority() )->build(
					revisionId: $this->settingsPage()->exists() ? $this->settingsPage()->getLatestRevID() : null
				),
			],
		] );

		if ( $subPage === 'setup' ) {
			$this->getOutput()->setPageTitleMsg( $this->msg( 'neowiki-bundle-setup-title' ) );
		}

		$this->getOutput()->addHTML( Html::element( 'div', array_merge(
			[ 'id' => 'ext-neowiki-data-model' ],
			$subPage === 'setup' ? [ 'data-mw-neowiki-view' => 'setup' ] : []
		) ) );
	}

	/**
	 * As on MediaWiki:NeoWiki itself, where the settings the setup asks for are kept.
	 */
	private function canEditSettings( NeoWikiExtension $extension ): bool {
		return $extension->isInWikiConfigEnabled() && $this->getAuthority()->probablyCan( 'edit', $this->settingsPage() );
	}

	private function settingsPage(): Title {
		return MediaWikiServices::getInstance()->getTitleFactory()->makeTitle( NS_MEDIAWIKI, NeoWikiExtension::CONFIG_PAGE_TITLE );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-datamodel' );
	}

}
