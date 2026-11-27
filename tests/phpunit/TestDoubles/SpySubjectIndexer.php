<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectIndexer;

class SpySubjectIndexer extends SubjectIndexer {

	/** @var list<RevisionRecord> */
	public array $indexedRevisions = [];

	public function __construct() {
	}

	public function indexRevision( RevisionRecord $revisionRecord ): void {
		$this->indexedRevisions[] = $revisionRecord;
	}

}
