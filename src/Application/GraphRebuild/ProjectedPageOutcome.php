<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use Throwable;

/**
 * What became of one page a rebuild batch reached.
 *
 * Whether the store was offered the page at all is separate from whether it took it, because a batch
 * failing in its entirety is read as the store having gone — and a page the walk found but the wiki has
 * since dropped never reached the store, so it says nothing either way.
 */
readonly class ProjectedPageOutcome {

	private function __construct(
		public bool $wasOfferedToTheStore,
		public ?Throwable $failure,
	) {
	}

	public static function projected(): self {
		return new self( wasOfferedToTheStore: true, failure: null );
	}

	public static function refused( Throwable $failure ): self {
		return new self( wasOfferedToTheStore: true, failure: $failure );
	}

	/**
	 * The page held nothing to project: the wiki no longer has it, or its latest revision dropped the
	 * Subject the walk found.
	 */
	public static function skipped(): self {
		return new self( wasOfferedToTheStore: false, failure: null );
	}

}
