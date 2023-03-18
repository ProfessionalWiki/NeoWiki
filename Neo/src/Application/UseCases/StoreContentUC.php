<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\UseCases;

use MediaWiki\Revision\RenderedRevision;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectContent;

class StoreContentUC {

	public function __construct(
		private readonly QueryStore $queryStore,
	) {
	}

	public function storeContent( RenderedRevision $renderedRevision, UserIdentity $user ): void {
		foreach ( $renderedRevision->getRevision()->getSlots()->getSlots() as $slot ) {
			$content = $slot->getContent();

			if ( $content instanceof SubjectContent ) {
				$this->queryStore->saveSubject( $content->getSubject() );
			}
		}
	}

}
