<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

interface SubjectAuthorizer {

	public function canCreateMainSubject( ?PageId $pageId ): bool;

	public function canCreateChildSubject( ?PageId $pageId ): bool;

	public function canEditSubject( ?PageId $pageId ): bool;

	public function canDeleteSubject( ?PageId $pageId ): bool;

}
