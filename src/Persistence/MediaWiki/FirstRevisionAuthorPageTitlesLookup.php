<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Persistence\ImportedPageTitlesLookup;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Finds the pages the import owns by first-revision authorship: a page whose first revision is the
 * importer's was created by the import, while one the importer merely edited later was not. This is
 * why the actor-narrowed candidates are confirmed against the first revision rather than trusting
 * rev_parent_id being zero.
 */
class FirstRevisionAuthorPageTitlesLookup implements ImportedPageTitlesLookup {

	public function __construct(
		private readonly IReadableDatabase $db,
		private readonly ActorNormalization $actorNormalization,
		private readonly RevisionLookup $revisionLookup,
		private readonly TitleFactory $titleFactory,
		private readonly UserIdentity $importer,
	) {
	}

	/**
	 * @return Title[]
	 */
	public function getImportedPageTitles(): array {
		$actorId = $this->actorNormalization->findActorId( $this->importer, $this->db );

		if ( $actorId === null ) {
			return [];
		}

		$titles = [];

		foreach ( $this->getPageIdsAuthoredBy( $actorId ) as $pageId ) {
			$title = $this->titleFactory->newFromID( $pageId );

			if ( $title !== null && $this->firstRevisionIsByImporter( $title ) ) {
				$titles[] = $title;
			}
		}

		return $titles;
	}

	/**
	 * @return int[] Ids of the still-existing pages the importer authored any revision of.
	 */
	private function getPageIdsAuthoredBy( int $actorId ): array {
		$pageIds = $this->db->newSelectQueryBuilder()
			->select( 'rev_page' )
			->distinct()
			->from( 'revision' )
			->where( [ 'rev_actor' => $actorId ] )
			->orderBy( 'rev_page' )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $pageIds );
	}

	private function firstRevisionIsByImporter( Title $title ): bool {
		$firstRevision = $this->revisionLookup->getFirstRevision( $title );

		return $firstRevision !== null
			&& $this->importer->equals( $firstRevision->getUser( RevisionRecord::RAW ) );
	}

}
