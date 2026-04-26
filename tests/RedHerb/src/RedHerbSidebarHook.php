<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use Closure;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Skin;

class RedHerbSidebarHook implements SidebarBeforeOutputHook {

	private Closure $pageHasMainSubject;

	public function __construct( ?Closure $pageHasMainSubject = null ) {
		$this->pageHasMainSubject = $pageHasMainSubject ?? static fn ( Title $title ): bool =>
			NeoWikiExtension::getInstance()->newViewHtmlBuilder()->pageHasMainSubject( $title );
	}

	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$links = [
			[
				'id' => 'redherb-sidebar-subject-finder',
				'text' => wfMessage( 'redherb-sidebar-subject-finder' )->text(),
				'href' => Title::makeTitle( NS_SPECIAL, 'RedHerbSubjectFinder' )->getLocalURL(),
			],
		];

		$title = $skin->getTitle();
		if ( $title !== null && $title->exists() ) {
			$links[] = [
				'id' => 'redherb-sidebar-create-child-company',
				'text' => wfMessage( 'redherb-sidebar-create-child-company' )->text(),
				'href' => '#',
				'class' => 'ext-redherb-create-child-company-trigger',
			];

			if ( ( $this->pageHasMainSubject )( $title ) ) {
				$links[] = [
					'id' => 'redherb-sidebar-edit-main-subject',
					'text' => wfMessage( 'redherb-sidebar-edit-main-subject' )->text(),
					'href' => '#',
					'class' => 'ext-redherb-edit-main-subject-trigger',
				];
			}
		}

		$sidebar['redherb-sidebar'] = $links;
	}

}
