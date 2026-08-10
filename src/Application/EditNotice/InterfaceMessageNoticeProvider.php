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
		$namespaceKey = self::KEY_PREFIX . $context->namespaceId;

		return [
			$namespaceKey,
			...$this->pageKeys( $namespaceKey, $context ),
			...$this->schemaKeys( $context ),
		];
	}

	/**
	 * Core gives a subpage-enabled namespace one key per ancestor, so a notice on a parent page
	 * covers its whole subtree, and flattens the path everywhere else. Both branches are mirrored
	 * so admins can carry their expectations over from core's edit notices.
	 *
	 * @return string[]
	 */
	private function pageKeys( string $namespaceKey, SubjectEditNoticeContext $context ): array {
		if ( !$context->namespaceHasSubpages ) {
			return [ $namespaceKey . '-' . self::asKeySegment( $context->pageDbKey ) ];
		}

		$keys = [];
		$ancestorKey = $namespaceKey;

		foreach ( explode( '/', $context->pageDbKey ) as $segment ) {
			$ancestorKey .= '-' . self::asKeySegment( $segment );
			$keys[] = $ancestorKey;
		}

		return $keys;
	}

	/**
	 * @return string[]
	 */
	private function schemaKeys( SubjectEditNoticeContext $context ): array {
		if ( $context->schemaName === null ) {
			return [];
		}

		return [ self::KEY_PREFIX . 'schema-' . self::asKeySegment( $context->schemaName ) ];
	}

	/**
	 * A slash would make the message page a subpage, which collides with the language subpages the
	 * MediaWiki namespace uses. Spaces become underscores so the key names the message page an admin
	 * actually creates, which is what the response echoes back.
	 */
	private static function asKeySegment( string $value ): string {
		return strtr( $value, [ '/' => '-', ' ' => '_' ] );
	}

}
