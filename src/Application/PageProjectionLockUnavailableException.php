<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use RuntimeException;

class PageProjectionLockUnavailableException extends RuntimeException {

	public function __construct( PageId $pageId ) {
		parent::__construct(
			'Page ' . $pageId->id . ' was not projected: another projection of it held on to the page for too long.'
		);
	}

}
