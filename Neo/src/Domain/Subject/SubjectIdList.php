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
		$ids = [];

		foreach ( $subjectIds as $id ) {
			$ids[$id->text] = $id;
		}

		$this->subjectIds = $ids;
	}

	/**
	 * @return array<string, SubjectId>
	 */
	public function asArray(): array {
		return $this->subjectIds;
	}

	public function asStringArray(): array {
		return array_keys( $this->subjectIds );
	}

}
