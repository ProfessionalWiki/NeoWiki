<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Message\Message;
use SpecialPage;

class SpecialSchemas extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Schemas' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		$this->getOutput()->addModuleStyles( [ 'ext.neowiki.styles' ] );
		$this->getOutput()->addModules( [ 'ext.neowiki' ] );
		$this->getOutput()->addHTML( '<div id="ext-neowiki-schemas"></div>' );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-schemas' );
	}

}
