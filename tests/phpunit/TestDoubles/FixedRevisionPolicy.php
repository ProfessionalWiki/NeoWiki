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
		private readonly ?int $answeredPageId,
	) {
	}

	public static function publishing( RevisionRecord $revision ): self {
		return new self(
			substitutes: true,
			published: $revision,
			readable: true,
			answeredPageId: $revision->getPageId()
		);
	}

	public static function publishingNothing(): self {
		return new self( substitutes: true, published: null, readable: true, answeredPageId: null );
	}

	/**
	 * Only that page publishes nothing, as an approval extension does while one page waits for review.
	 */
	public static function publishingNothingFromPage( int $pageId ): self {
		return new self( substitutes: true, published: null, readable: true, answeredPageId: $pageId );
	}

	public static function hidingEveryRevision(): self {
		return new self( substitutes: false, published: null, readable: false, answeredPageId: null );
	}

	/**
	 * Answers per page, as an approval extension does: a policy naming a page answers for that page
	 * alone, and any other page — a Schema page the projection resolves, say — passes through.
	 */
	public function publishedRevision( RevisionRecord $revision ): ?RevisionRecord {
		if ( !$this->substitutes ) {
			return $revision;
		}

		if ( $this->answeredPageId === null || $this->answeredPageId === $revision->getPageId() ) {
			return $this->published;
		}

		return $revision;
	}

	public function revisionIsReadableBy( RevisionRecord $revision, Authority $viewer ): bool {
		return $this->readable;
	}

}
