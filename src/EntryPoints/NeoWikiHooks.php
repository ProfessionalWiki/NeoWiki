<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Output\OutputPage;
use Skin;

class NeoWikiHooks {

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		if ( !$out->isArticle() ) {
			return;
		}

		// TODO: just for testing
		if ( str_contains( $out->getHTML(), 'infobox' ) ) {
			$out->addHTML( '<div id="neowiki-infobox"></div>' );
			$out->addModules( 'ext.neowiki.infobox' );
		} else {
			$out->addHTML( '<div id="neowiki-add-button"></div>' );
			$out->addModules( 'ext.neowiki.addButton' );
		}
	}

}
