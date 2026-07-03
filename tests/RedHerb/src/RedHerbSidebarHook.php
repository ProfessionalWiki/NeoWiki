<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Skin;

class RedHerbSidebarHook implements SidebarBeforeOutputHook {

	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$links = [
			[
				'id' => 'redherb-sidebar-subject-finder',
				'text' => $skin->msg( 'redherb-sidebar-subject-finder' )->text(),
				'href' => Title::makeTitle( NS_SPECIAL, 'RedHerbSubjectFinder' )->getLocalURL(),
			],
		];

		$title = $skin->getTitle();
		if ( $title !== null && $title->exists() ) {
			$extension = NeoWikiExtension::getInstance();
			$authorizer = $extension->newSubjectAuthorizer( $skin->getAuthority() );
			$pageId = new PageId( $title->getArticleID() );

			if ( $authorizer->canCreateChildSubject( $pageId ) ) {
				$links[] = [
					'id' => 'redherb-sidebar-create-child-company',
					'text' => $skin->msg( 'redherb-sidebar-create-child-company' )->text(),
					'href' => '#',
					'class' => 'ext-redherb-create-child-company-trigger',
				];
			}

			if (
				$authorizer->canEditSubject( $pageId )
				&& $extension->newPageSubjectsLookup()
					->pageHasMainSubject( $pageId )
			) {
				$links[] = [
					'id' => 'redherb-sidebar-edit-main-subject',
					'text' => $skin->msg( 'redherb-sidebar-edit-main-subject' )->text(),
					'href' => '#',
					'class' => 'ext-redherb-edit-main-subject-trigger',
				];
			}
		}

		$sidebar['redherb-sidebar'] = $links;
	}

}
