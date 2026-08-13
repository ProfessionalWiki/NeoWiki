<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\EditNotice\SubjectEditNoticeEnvironment;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;

/**
 * ContentStabilization reads the main request context when describing pending revisions, and
 * VisualEditor sets the title for the same reason (T307852) — while calling it a dirty hack, which
 * it is: this writes to process-global state. It runs only after the read gate has passed, so no
 * title is published for a page the caller may not read.
 */
class RequestContextSubjectEditNoticeEnvironment implements SubjectEditNoticeEnvironment {

	private ?Title $replacedTitle = null;

	public function __construct(
		private readonly TitleFactory $titleFactory
	) {
	}

	public function prepareFor( SubjectEditNoticeContext $context ): void {
		// What getTitle() answers rather than the raw internal value, which is private: it falls back
		// to $wgTitle when unset, so restoring this keeps the answer the same either way.
		$this->replacedTitle = RequestContext::getMain()->getTitle();

		RequestContext::getMain()->setTitle(
			$this->titleFactory->makeTitle( $context->namespaceId, $context->pageDbKey )
		);
	}

	public function restore(): void {
		RequestContext::getMain()->setTitle( $this->replacedTitle );
	}

}
