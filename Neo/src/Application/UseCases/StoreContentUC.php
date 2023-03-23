<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\UseCases;

use MediaWiki\Revision\RenderedRevision;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\Domain\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectContent;

class StoreContentUC {

	public function __construct(
		private readonly QueryStore $queryStore,
	) {
	}

	public function storeContent( RenderedRevision $renderedRevision, UserIdentity $user ): void {
		$allSubjects = new SubjectMap();

		foreach ( $renderedRevision->getRevision()->getSlots()->getSlots() as $slot ) {
			$content = $slot->getContent();

			if ( $content instanceof SubjectContent ) {
				$allSubjects->append( $content->getSubjects() );
			}
		}

		$this->queryStore->savePage(
			pageId: $renderedRevision->getRevision()->getPageId(),
			pageTitle: $renderedRevision->getRevision()->getPageAsLinkTarget()->getText(),
			subjects: $allSubjects
		);
	}

}
