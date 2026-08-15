<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\EditNotice;

interface EditNoticeMessageRenderer {

	/**
	 * Renders an interface message to HTML, or returns null when it contributes nothing: the message
	 * does not exist, an admin disabled it, or it renders empty.
	 *
	 * The page is passed so magic words inside a notice resolve against the page being edited rather
	 * than against whatever page happens to be rendering.
	 */
	public function render( string $messageKey, int $namespaceId, string $pageDbKey ): ?string;

}
