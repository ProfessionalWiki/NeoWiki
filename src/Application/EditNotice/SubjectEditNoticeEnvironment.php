<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\EditNotice;

use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;

/**
 * Makes the ambient request state agree with the page whose notices are being collected, before any
 * provider runs. Providers are handed a context, but a third-party one may instead read the main
 * request context, which outside a page request carries no page at all.
 */
interface SubjectEditNoticeEnvironment {

	public function prepareFor( SubjectEditNoticeContext $context ): void;

	/**
	 * Puts back what {@see prepareFor} replaced, so the rest of the request is unaffected.
	 */
	public function restore(): void;

}
