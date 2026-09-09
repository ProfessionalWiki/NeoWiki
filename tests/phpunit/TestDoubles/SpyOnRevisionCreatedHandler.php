<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;

class SpyOnRevisionCreatedHandler extends OnRevisionCreatedHandler {

	/** @var list<RevisionRecord> */
	public array $calls = [];

	public PageRefreshOutcome $outcome = PageRefreshOutcome::Refreshed;

	public function __construct() {
	}

	public function onRevisionCreated( RevisionRecord $revisionRecord ): PageRefreshOutcome {
		$this->calls[] = $revisionRecord;
		return $this->outcome;
	}

}
