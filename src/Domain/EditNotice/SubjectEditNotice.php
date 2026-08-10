<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\EditNotice;

/**
 * A message shown to a user before they edit a Subject.
 */
readonly class SubjectEditNotice {

	/**
	 * @param string $key Identifies the notice across requests, so a client can key or dismiss one.
	 * @param string $html Rendered, and already escaped or sanitized by whoever supplied it.
	 */
	public function __construct(
		public string $key,
		public string $html,
	) {
	}

}
