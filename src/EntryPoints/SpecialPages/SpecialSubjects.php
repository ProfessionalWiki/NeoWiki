<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\DocumentationUrl;

/**
 * Every Subject on the wiki, newest first, to filter by Schema and search. Special:Subjects/<Schema> opens on one
 * Schema's Subjects.
 */
class SpecialSubjects extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Subjects' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );
		$this->addHelpLink( DocumentationUrl::Subjects->value, true );

		$extension = NeoWikiExtension::getInstance();
		$out = $this->getOutput();
		$extension->newFrontendModuleLoader()->load( $out, $this->getSkin() );

		if ( !$extension->isSubjectListAvailable() ) {
			$out->addHTML( Html::noticeBox( $this->msg( 'neowiki-subjects-unavailable' )->escaped(), '' ) );
			return;
		}

		$attributes = [ 'id' => 'ext-neowiki-subjects' ];

		// As Schema names are read everywhere: "Help:Person" names the Schema page Schema:Help:Person.
		$schemaTitle = MediaWikiServices::getInstance()->getTitleFactory()
			->makeTitleSafe( NeoWikiExtension::NS_SCHEMA, $subPage ?? '' );

		if ( $schemaTitle !== null ) {
			$attributes['data-mw-neowiki-schema'] = $schemaTitle->getText();
		}

		$out->addHTML( Html::element( 'div', $attributes ) );
	}

	public function getGroupName(): string {
		return 'neowiki';
	}

	public function getDescription(): Message {
		return $this->msg( 'neowiki-special-subjects' );
	}

}
