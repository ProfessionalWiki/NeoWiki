<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use WeakMap;

/**
 * A chain of Host Subjects is followed for at most {@see self::MAX_NAMED_HOST_SUBJECTS}, and each page's Host Subjects
 * are found once, so that a page holding a long chain cannot make every read of it slow.
 */
class HostSubjectBuilder {

	public const int MAX_NAMED_HOST_SUBJECTS = 10;

	/**
	 * @var WeakMap<PageSubjects, array<string, Subject>>
	 */
	private WeakMap $hostSubjectsByPage;

	public function __construct(
		private readonly DependentSubjectRules $dependentSubjectRules,
	) {
		$this->hostSubjectsByPage = new WeakMap();
	}

	/**
	 * Null for a Subject that is not a Dependent Subject, or that no Subject on its page points at.
	 *
	 * @param Subject $subject A Subject without a chosen name: {@see SubjectDisplayName::labelOrPageName()} is null.
	 * @param PageSubjects $pageSubjects Must not change between calls: its Host Subjects are found once per instance.
	 */
	public function build( Subject $subject, PageSubjects $pageSubjects, string $pageName ): ?HostSubject {
		if ( !$this->dependentSubjectRules->isDependent( $subject ) ) {
			return null;
		}

		$this->hostSubjectsByPage[$pageSubjects] ??= $this->dependentSubjectRules->hostSubjectsOn( $pageSubjects );

		return $this->hostSubjectAlong(
			$subject,
			$this->hostSubjectsByPage[$pageSubjects],
			$pageSubjects,
			$pageName,
			[ $subject->id->text => true ]
		);
	}

	/**
	 * @param array<string, Subject> $hostSubjects
	 * @param array<string, true> $chain The ids named so far, to stop at a cycle, which a raw write can leave.
	 */
	private function hostSubjectAlong(
		Subject $subject,
		array $hostSubjects,
		PageSubjects $pageSubjects,
		string $pageName,
		array $chain
	): ?HostSubject {
		$host = $hostSubjects[$subject->id->text] ?? null;

		if ( $host === null || array_key_exists( $host->id->text, $chain ) || count( $chain ) > self::MAX_NAMED_HOST_SUBJECTS ) {
			return null;
		}

		$chosenName = SubjectDisplayName::labelOrPageName( $host, $pageSubjects, $pageName );

		return new HostSubject(
			id: $host->id->text,
			schema: $host->getSchemaReference()->toJson(),
			displayName: $chosenName ?? $host->getSchemaReference()->getText(),
			displayNameIsGenerated: $chosenName === null,
			hostSubject: $chosenName === null
				? $this->hostSubjectAlong( $host, $hostSubjects, $pageSubjects, $pageName, $chain + [ $host->id->text => true ] )
				: null,
		);
	}

}
