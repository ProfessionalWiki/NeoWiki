<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Output\OutputPage;
use Skin;

class NeoWikiHooks {

	public static function onSkinAfterPortlet( Skin $skin, string $portletName, string &$html ) {
		// TODO: just for testing
		if ( $portletName === 'views' ) {
			if ( str_contains( $skin->getOutput()->getHTML(), 'add' ) ) {
				$html .= '<div id="neowiki-add-button"></div>';
				$skin->getOutput()->addModules( 'ext.neowiki.addButton' );
			}
		}
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		if ( !$out->isArticle() ) {
			return;
		}

		// TODO: just for testing
		if ( str_contains( $out->getHTML(), 'infobox' ) ) {
			$out->addHTML( '<div id="neowiki-infobox"></div>' );
			$out->addModules( 'ext.neowiki.infobox' );
		}

	}

}
