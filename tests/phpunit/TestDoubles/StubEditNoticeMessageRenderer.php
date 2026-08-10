<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\EditNotice\EditNoticeMessageRenderer;

class StubEditNoticeMessageRenderer implements EditNoticeMessageRenderer {

	/**
	 * @param array<string, string> $renderedMessages Message key to rendered HTML. Absent keys render nothing.
	 */
	public function __construct(
		private readonly array $renderedMessages
	) {
	}

	public function render( string $messageKey, int $namespaceId, string $pageDbKey ): ?string {
		return $this->renderedMessages[$messageKey] ?? null;
	}

}
