<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

class SubjectIdList {

	/**
	 * @var SubjectId[]
	 */
	private readonly array $subjectIds;

	/**
	 * @param SubjectId[] $subjectIds
	 */
	public function __construct( array $subjectIds ) {
		$this->subjectIds = $subjectIds;
	}

	/**
	 * @return SubjectId[]
	 */
	public function asArray(): array {
		return $this->subjectIds;
	}

	public function asStringArray(): array {
		return array_map(
			fn( SubjectId $subjectId ): string => $subjectId->text,
			$this->subjectIds
		);
	}

}
