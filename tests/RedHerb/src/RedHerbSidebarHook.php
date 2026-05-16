<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use Closure;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Skin;

class RedHerbSidebarHook implements SidebarBeforeOutputHook {

	private Closure $pageHasMainSubject;

	/**
	 * @var Closure(Authority): SubjectAuthorizer
	 */
	private Closure $newSubjectAuthorizer;

	/**
	 * @param ?Closure(Title): bool $pageHasMainSubject
	 * @param ?Closure(Authority): SubjectAuthorizer $newSubjectAuthorizer
	 */
	public function __construct(
		?Closure $pageHasMainSubject = null,
		?Closure $newSubjectAuthorizer = null
	) {
		$this->pageHasMainSubject = $pageHasMainSubject ?? static fn ( Title $title ): bool =>
			NeoWikiExtension::getInstance()->newPageSubjectsLookup()
				->pageHasMainSubject( new PageId( $title->getArticleID() ) );

		$this->newSubjectAuthorizer = $newSubjectAuthorizer ?? static fn ( Authority $authority ): SubjectAuthorizer =>
			NeoWikiExtension::getInstance()->newSubjectAuthorizer( $authority );
	}

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
			$authorizer = ( $this->newSubjectAuthorizer )( $skin->getAuthority() );

			if ( $authorizer->canCreateChildSubject() ) {
				$links[] = [
					'id' => 'redherb-sidebar-create-child-company',
					'text' => $skin->msg( 'redherb-sidebar-create-child-company' )->text(),
					'href' => '#',
					'class' => 'ext-redherb-create-child-company-trigger',
				];
			}

			if ( ( $this->pageHasMainSubject )( $title ) && $authorizer->canEditSubject() ) {
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
