<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;

/**
 * Mints batches of fresh, mutually distinct Subject IDs for client-side wiring of interlinked
 * imports. Generation stays server-owned so the ID scheme can evolve without breaking importers.
 */
readonly class SubjectIdMinter {

	public function __construct(
		private IdGenerator $idGenerator,
	) {
	}

	/**
	 * @return list<SubjectId> exactly $count distinct ids
	 */
	public function mint( int $count ): array {
		$ids = [];

		while ( count( $ids ) < $count ) {
			$id = SubjectId::createNew( $this->idGenerator );
			$ids[$id->text] = $id;
		}

		return array_values( $ids );
	}

}
