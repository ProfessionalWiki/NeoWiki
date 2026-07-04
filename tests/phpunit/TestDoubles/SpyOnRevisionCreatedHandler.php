<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;

class SpyOnRevisionCreatedHandler extends OnRevisionCreatedHandler {

	/** @var list<array{revision: RevisionRecord, user: ?UserIdentity}> */
	public array $calls = [];

	public bool $pageWasWritten = true;

	public function __construct() {
	}

	public function onRevisionCreated( RevisionRecord $revisionRecord, ?UserIdentity $user ): bool {
		$this->calls[] = [ 'revision' => $revisionRecord, 'user' => $user ];
		return $this->pageWasWritten;
	}

}
