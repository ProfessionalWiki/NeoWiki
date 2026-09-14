<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Finds the Subjects that reference a given one, by asking a query projection which Subjects hold an
 * edge into it.
 *
 * Candidates, not an answer: a projection lags the wiki and can hold an edge a Subject no longer
 * holds (#1135), so callers load each candidate from the wiki and keep only those whose current
 * Statements still target it. Callers also gate each candidate on the caller's read access, since
 * nothing here does.
 */
interface ReferencingSubjectLookup {

	/**
	 * @param int $limit Maximum candidates to return. Callers must cap this to a small value: every
	 *   candidate costs a Subject read and a permission check, so an unbounded limit is unbounded
	 *   work. The REST entry point refuses a requested size above 50, and asks for a small multiple
	 *   of it to leave room for the candidates it drops.
	 * @return SubjectId[] In a deterministic order, without duplicates and without $target itself.
	 */
	public function getIdsOfSubjectsReferencing( SubjectId $target, int $limit ): array;

}
