<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\EditNotice;

/**
 * A message shown to a user before they edit a Subject.
 */
readonly class SubjectEditNotice {

	/**
	 * @param string $key Identifies the notice across requests, so a client can key or dismiss one.
	 * @param string $html Rendered, and already escaped or sanitized by whoever supplied it. For an
	 *  admin-written notice that is MediaWiki's parser; the channel grants no capability an
	 *  editinterface holder lacks, since they can already place parser-sanitized HTML on every page
	 *  view through MediaWiki:Sitenotice. Note they cannot necessarily run script: editsitejs belongs
	 *  to interface-admin, not sysop, so the parser is the guard here rather than a conceded right.
	 */
	public function __construct(
		public string $key,
		public string $html,
	) {
	}

}
