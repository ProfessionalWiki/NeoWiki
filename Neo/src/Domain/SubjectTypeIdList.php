<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

/**
 * "Instance Of"
 */
class SubjectTypeIdList {

	public function __construct(
		/**
		 * @var SubjectTypeId[]
		 */
		public readonly array $ids
	) {
	}

	/**
	 * @return string[]
	 */
	public function toStringArray(): array {
		return array_map(
			fn( SubjectTypeId $id ) => $id->text,
			$this->ids
		);
	}

}
