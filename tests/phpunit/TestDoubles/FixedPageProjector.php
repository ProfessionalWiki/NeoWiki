<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\Rdf\PageProjector;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Rdf\QuadList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * A {@see PageProjector} that returns a preset {@see QuadList} regardless of the page, so tests can
 * exercise the store's update building and serialization against known quads.
 */
readonly class FixedPageProjector implements PageProjector {

	public function __construct(
		private QuadList $quads,
	) {
	}

	public function projectPage( Page $page ): QuadList {
		return $this->quads;
	}

	public function projectSubject( Page $page, SubjectId $subjectId ): QuadList {
		return $this->quads;
	}

}
