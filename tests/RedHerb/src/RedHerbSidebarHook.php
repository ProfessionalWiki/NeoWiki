<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Title\Title;
use Skin;

class RedHerbSidebarHook implements SidebarBeforeOutputHook {

	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$sidebar['redherb-sidebar'] = [
			[
				'id' => 'redherb-sidebar-create-child-company',
				'text' => wfMessage( 'redherb-sidebar-create-child-company' )->text(),
				'href' => '#',
				'class' => 'ext-redherb-create-child-company-trigger',
			],
			[
				'id' => 'redherb-sidebar-subject-finder',
				'text' => wfMessage( 'redherb-sidebar-subject-finder' )->text(),
				'href' => Title::makeTitle( NS_SPECIAL, 'RedHerbSubjectFinder' )->getLocalURL(),
			],
		];
	}

}
