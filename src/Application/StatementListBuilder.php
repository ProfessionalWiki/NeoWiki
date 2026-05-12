<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;

readonly class StatementListBuilder {

	public function __construct(
		private StatementListPatcher $patcher,
	) {
	}

	/**
	 * @param array<string, mixed> $statements
	 */
	public function build( array $statements ): StatementList {
		return $this->patcher->buildStatementList(
			statements: new StatementList( [] ),
			patch: $statements,
		);
	}

}
