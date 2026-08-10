<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\EditNotice;

use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNotice;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeProvider;

/**
 * Notices wiki admins write as interface messages, following the convention core uses for its own
 * edit notices. None of these keys ship in the extension's i18n: a key only resolves once an admin
 * creates the corresponding MediaWiki-namespace page.
 */
readonly class InterfaceMessageNoticeProvider implements SubjectEditNoticeProvider {

	private const string KEY_PREFIX = 'neowiki-editnotice-';

	public function __construct(
		private EditNoticeMessageRenderer $renderer
	) {
	}

	public function getNotices( SubjectEditNoticeContext $context ): array {
		$notices = [];

		foreach ( $this->messageKeys( $context ) as $messageKey ) {
			$html = $this->renderer->render( $messageKey, $context->namespaceId, $context->pageDbKey );

			if ( $html !== null ) {
				$notices[] = new SubjectEditNotice( key: $messageKey, html: $html );
			}
		}

		return $notices;
	}

	/**
	 * Broadest first, so a page notice reads as a refinement of its namespace's.
	 *
	 * @return string[]
	 */
	private function messageKeys( SubjectEditNoticeContext $context ): array {
		$keys = [
			self::KEY_PREFIX . $context->namespaceId,
			self::KEY_PREFIX . $context->namespaceId . '-' . self::asKeySegment( $context->pageDbKey ),
		];

		if ( $context->schemaName !== null ) {
			$keys[] = self::KEY_PREFIX . 'schema-' . self::asKeySegment( $context->schemaName );
		}

		return $keys;
	}

	/**
	 * A slash would make the message page a subpage, which collides with the language subpages the
	 * MediaWiki namespace uses. Core flattens the same way.
	 */
	private static function asKeySegment( string $value ): string {
		return strtr( $value, '/', '-' );
	}

}
