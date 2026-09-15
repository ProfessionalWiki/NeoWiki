<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Where working with the wiki as a database starts: the Schemas it holds, a way to create a Subject with
 * any of them, and the other NeoWiki pages.
 */
class SpecialNeoWiki extends SpecialPage {

	public function __construct() {
		parent::__construct( 'NeoWiki' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $this->getOutput(), $this->getSkin() );

		$attributes = [ 'id' => 'ext-neowiki-overview' ];

		// Special:GraphStores is restricted, and Special:SpecialPages lists it only to those who may use
		// it. Offering a link here to anyone else would lead to a permissions error instead of a page.
		if ( $this->getAuthority()->isAllowed( NeoWikiExtension::ADMIN_RIGHT ) ) {
			$attributes['data-mw-neowiki-can-manage-graph-stores'] = 'true';
		}

		if ( $this->canEditConfiguration() ) {
			$attributes['data-mw-neowiki-can-edit-configuration'] = 'true';
		}

		$this->getOutput()->addHTML( Html::element( 'div', $attributes ) );
	}

	/**
	 * The map offers the configuration page only to those who may edit it, as with Graph stores.
	 */
	private function canEditConfiguration(): bool {
		if ( !NeoWikiExtension::getInstance()->isInWikiConfigEnabled() ) {
			return false;
		}

		$configTitle = MediaWikiServices::getInstance()->getTitleFactory()
			->makeTitle( NS_MEDIAWIKI, NeoWikiExtension::CONFIG_PAGE_TITLE );

		return $this->getAuthority()->probablyCan( 'edit', $configTitle );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-neowiki' );
	}

}
