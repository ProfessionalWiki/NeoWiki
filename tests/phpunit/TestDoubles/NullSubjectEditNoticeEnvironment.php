<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\EditNotice\SubjectEditNoticeEnvironment;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;

class NullSubjectEditNoticeEnvironment implements SubjectEditNoticeEnvironment {

	public ?SubjectEditNoticeContext $preparedFor = null;

	public bool $restored = false;

	public function prepareFor( SubjectEditNoticeContext $context ): void {
		$this->preparedFor = $context;
	}

	public function restore(): void {
		$this->restored = true;
	}

}
