<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use PermissionsError;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class SpecialCreateSubject extends SpecialPage {

	use EnforcesRestriction;

	public function __construct() {
		parent::__construct( 'CreateSubject' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		// A special page names one restriction; the Subject write needs edit, and the page it goes on
		// needs this as well.
		if ( !$this->getAuthority()->isAllowed( 'createpage' ) ) {
			throw new PermissionsError( 'createpage' );
		}

		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $this->getOutput(), $this->getSkin() );

		$attributes = [ 'id' => 'ext-neowiki-create-subject' ];

		if ( $subPage !== null && $subPage !== '' ) {
			$attributes['data-mw-neowiki-schema'] = $subPage;
		}

		$this->getOutput()->addHTML( Html::element( 'div', $attributes ) );
	}

	public function getRestriction(): string {
		return 'edit';
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-createsubject' );
	}

}
