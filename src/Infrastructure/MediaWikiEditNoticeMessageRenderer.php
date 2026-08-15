<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Title\TitleFactory;
use MessageLocalizer;
use ProfessionalWiki\NeoWiki\Application\EditNotice\EditNoticeMessageRenderer;

readonly class MediaWikiEditNoticeMessageRenderer implements EditNoticeMessageRenderer {

	public function __construct(
		private MessageLocalizer $messageLocalizer,
		private TitleFactory $titleFactory,
	) {
	}

	public function render( string $messageKey, int $namespaceId, string $pageDbKey ): ?string {
		$message = $this->messageLocalizer->msg( $messageKey )
			->page( $this->titleFactory->makeTitle( $namespaceId, $pageDbKey ) );

		// A key with no MediaWiki-namespace page behind it, or one an admin set to "-", contributes nothing.
		if ( !$message->exists() || $message->isDisabled() ) {
			return null;
		}

		$html = $message->parseAsBlock();

		// Notices carry parser logic often enough that a non-empty message still renders to nothing,
		// which would otherwise reach the client as an empty message frame. Core guards the same way.
		return trim( $html ) === '' ? null : $html;
	}

}
