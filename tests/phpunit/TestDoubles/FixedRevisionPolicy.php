<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use Closure;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;

class FixedRevisionPolicy implements RevisionPolicy {

	/**
	 * @param Closure(RevisionRecord): bool $isReadable
	 */
	private function __construct(
		private readonly bool $substitutes,
		private readonly ?RevisionRecord $published,
		private readonly Closure $isReadable,
		private readonly ?int $answeredPageId,
	) {
	}

	public static function publishing( RevisionRecord $revision ): self {
		return new self(
			substitutes: true,
			published: $revision,
			isReadable: static fn (): bool => true,
			answeredPageId: $revision->getPageId()
		);
	}

	public static function publishingNothing(): self {
		return new self( substitutes: true, published: null, isReadable: static fn (): bool => true, answeredPageId: null );
	}

	/**
	 * Only that page publishes nothing, as an approval extension does while one page waits for review.
	 */
	public static function publishingNothingFromPage( int $pageId ): self {
		return new self( substitutes: true, published: null, isReadable: static fn (): bool => true, answeredPageId: $pageId );
	}

	public static function hidingEveryRevision(): self {
		return new self( substitutes: false, published: null, isReadable: static fn (): bool => false, answeredPageId: null );
	}

	/**
	 * Every page publishes what was written, and only that revision is hidden from the viewer, as an
	 * approval extension hides a draft from viewers who may not review it.
	 */
	public static function hidingRevision( RevisionRecord $hidden ): self {
		return new self(
			substitutes: false,
			published: null,
			isReadable: static fn ( RevisionRecord $revision ): bool => $revision->getId() !== $hidden->getId(),
			answeredPageId: null
		);
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
		return ( $this->isReadable )( $revision );
	}

}
