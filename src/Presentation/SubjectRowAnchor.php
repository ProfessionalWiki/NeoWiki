<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

/**
 * The DOM id — and, identically, the URL fragment — of a Subject's row on the Data tab
 * (`?action=subjects`). The subject-IRI dereference endpoint mints `#<domId>` into its `Location` when
 * the wiki targets the Data tab ({@see SubjectDereferenceTarget}), and the Data tab reads the fragment
 * back to expand, scroll to, and highlight the row.
 *
 * Single source of truth for this scheme on the PHP side. Its TypeScript counterpart is
 * `subjectRowDomId()` in `resources/ext.neowiki/src/presentation/subjectRowDomId.ts`; the two must stay
 * in lockstep. Each side has a test asserting the same literal example, so a prefix change on one side
 * alone breaks a test.
 */
final class SubjectRowAnchor {

	public const string DOM_ID_PREFIX = 'ext-neowiki-subject-row-';

	public static function domId( string $subjectId ): string {
		return self::DOM_ID_PREFIX . $subjectId;
	}

}
