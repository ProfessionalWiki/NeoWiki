<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Closure;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

readonly class DependentSubjectGuard {

	/**
	 * @param Closure(): SubjectLookup $subjectLookup Limited to the pages the writer may read. A closure, because
	 *   the lookup reads through the Subject repository, which holds this guard.
	 */
	public function __construct(
		private DependentSubjectRules $rules,
		private Closure $subjectLookup,
	) {
	}

	/**
	 * $after without the Relations to the Dependent Subjects the write removed, and without the Dependent
	 * Subjects it leaves unreached.
	 *
	 * @param SubjectIdList $suppliedIds The ids in the request's `dependentSubjects`.
	 *
	 * @throws DependentSubjectRulesViolatedException When $after breaks a rule $before does not.
	 */
	public function admit( PageSubjects $before, PageSubjects $after, SubjectIdList $suppliedIds ): PageSubjects {
		$after = $this->rules->withoutRelationsToRemovedDependentSubjects( $before, $after );

		$violations = $this->rules->newViolations(
			$before,
			$after,
			$this->getSubjects( $this->rules->targetsOnOtherPages( $before, $after ) ),
			$suppliedIds
		);

		if ( $violations !== [] ) {
			throw new DependentSubjectRulesViolatedException( $violations );
		}

		return $this->rules->withoutDependentSubjectsTheWriteLeavesUnreached( $before, $after );
	}

	private function getSubjects( SubjectIdList $ids ): SubjectMap {
		if ( $ids->asArray() === [] ) {
			return new SubjectMap();
		}

		return ( $this->subjectLookup )()->getSubjects( $ids );
	}

}
