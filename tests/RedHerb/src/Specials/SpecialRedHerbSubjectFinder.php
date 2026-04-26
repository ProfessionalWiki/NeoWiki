<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb\Specials;

use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;

class SpecialRedHerbSubjectFinder extends SpecialPage {

	public function __construct() {
		parent::__construct( 'RedHerbSubjectFinder' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		$out = $this->getOutput();
		NeoWikiHooks::addNeoWikiModules( $out, $this->getSkin() );
		$out->addModules( 'ext.redherb-sidebar' );

		$out->addHTML( Html::element( 'div', [ 'id' => 'ext-redherb-subject-finder' ] ) );
	}

	public function getDescription(): Message {
		return $this->msg( 'redherb-special-subject-finder' );
	}

}
