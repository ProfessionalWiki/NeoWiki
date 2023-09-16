<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Revision\RevisionRecord;
use Title;

interface SchemaRepository {
	public function saveSchema( Title $title, string $text, int $editFlag ): RevisionRecord|null;
}
