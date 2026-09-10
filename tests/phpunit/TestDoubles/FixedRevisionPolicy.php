<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;

class FixedRevisionPolicy implements RevisionPolicy {

	private function __construct(
		private readonly bool $substitutes,
		private readonly ?RevisionRecord $published,
		private readonly bool $readable,
	) {
	}

	public static function publishing( RevisionRecord $revision ): self {
		return new self( substitutes: true, published: $revision, readable: true );
	}

	public static function publishingNothing(): self {
		return new self( substitutes: true, published: null, readable: true );
	}

	public static function hidingEveryRevision(): self {
		return new self( substitutes: false, published: null, readable: false );
	}

	/**
	 * Answers per page, as an approval extension does: the fixed revision stands in only for revisions of
	 * its own page, and any other page — a Schema page the projection resolves, say — passes through.
	 */
	public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord {
		if ( !$this->substitutes ) {
			return $revision;
		}

		if ( $this->published === null || $this->published->getPageId() === $revision->getPageId() ) {
			return $this->published;
		}

		return $revision;
	}

	public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool {
		return $this->readable;
	}

}
