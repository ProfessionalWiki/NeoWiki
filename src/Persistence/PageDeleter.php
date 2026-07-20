<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use MediaWiki\Page\ProperPageIdentity;

interface PageDeleter {

	public function deletePage( ProperPageIdentity $page, string $reason ): PageDeletionStatus;

}
