<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class SpecialLayouts extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Layouts' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $this->getOutput(), $this->getSkin() );
		$this->getOutput()->addHTML( '<div id="ext-neowiki-layouts"></div>' );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-layouts' );
	}

}
