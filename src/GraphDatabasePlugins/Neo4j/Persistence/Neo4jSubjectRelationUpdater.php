<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Reconciles the outgoing relations of a single Subject with the relations it holds.
 */
class Neo4jSubjectRelationUpdater {

	public function __construct(
		private readonly SubjectId $subjectId,
		private readonly TypedRelationList $relations,
		private readonly TransactionInterface $transaction,
		private readonly string $wikiId,
		private readonly Neo4jOrphanCandidates $orphanCandidates,
	) {
	}

	public function updateRelations(): void {
		( new Neo4jPageRelationUpdater( $this->transaction, $this->wikiId, $this->orphanCandidates ) )
			->updateRelations( [ $this->subjectId->text => $this->relations ] );
	}

}
