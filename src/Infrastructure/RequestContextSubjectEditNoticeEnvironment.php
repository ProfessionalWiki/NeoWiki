<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\EditNotice\SubjectEditNoticeEnvironment;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;

/**
 * ContentStabilization reads the main request context when describing pending revisions, and
 * VisualEditor sets the title for the same reason (T307852) — while calling it a dirty hack, which
 * it is: this writes to process-global state. It runs only after the read gate has passed, so no
 * title is published for a page the caller may not read.
 */
readonly class RequestContextSubjectEditNoticeEnvironment implements SubjectEditNoticeEnvironment {

	public function __construct(
		private TitleFactory $titleFactory
	) {
	}

	public function prepareFor( SubjectEditNoticeContext $context ): void {
		RequestContext::getMain()->setTitle(
			$this->titleFactory->makeTitle( $context->namespaceId, $context->pageDbKey )
		);
	}

}
