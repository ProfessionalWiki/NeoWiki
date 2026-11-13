<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\GraphStoreProjector;

class SpyGraphStoreProjector extends GraphStoreProjector {

	/** @var list<RevisionRecord> */
	public array $projections = [];

	/** @var list<RenderedRevision|null> */
	public array $renderings = [];

	/** @var list<PageId> */
	public array $withdrawals = [];

	public function __construct() {
	}

	public function projectRevision( RevisionRecord $revisionRecord, ?RenderedRevision $rendering ): PageRefreshOutcome {
		$this->projections[] = $revisionRecord;
		$this->renderings[] = $rendering;
		return PageRefreshOutcome::Refreshed;
	}

	public function withdrawPage( PageId $pageId ): void {
		$this->withdrawals[] = $pageId;
	}

}
