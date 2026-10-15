<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use Exception;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Utils\MWTimestamp;
use ProfessionalWiki\NeoWiki\Application\HostSubjectBuilder;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\RecentlyEditedSubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummary;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use stdClass;
use TypeError;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Finds the Subjects the newest revisions changed by comparing each page's Subject slot with the one before it, so it
 * needs no graph store. Looks through the newest $revisionBound revisions and reads at most about $readBound pages
 * and edits; reaching either bound ends the list early.
 */
class DatabaseRecentlyEditedSubjectLookup implements RecentlyEditedSubjectLookup {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly NameTableStore $slotRoleStore,
		private readonly RevisionLookup $revisionLookup,
		private readonly RevisionPolicy $revisionPolicy,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly TitleFormatter $titleFormatter,
		private readonly HostSubjectBuilder $hostSubjectBuilder,
		private readonly int $revisionBound,
		private readonly int $readBound,
	) {
	}

	public function getRecentlyEditedSubjects( int $limit ): array {
		/** @var array<int, array{?RevisionRecord, ?PageSubjects}> $pages */
		$pages = [];
		/** @var array<string, array{Subject, PageSubjects, RevisionRecord, stdClass}> $edits */
		$edits = [];
		$reads = 0;

		foreach ( $this->newestRevisions() as $change ) {
			if ( $reads >= $this->readBound || count( $edits ) >= $limit ) {
				break;
			}

			if ( $change->slot_origin === null || $change->slot_origin === $change->parent_slot_origin ) {
				continue;
			}

			if ( !array_key_exists( $change->rev_page, $pages ) ) {
				$publishedRevision = $this->publishedRevision( (int)$change->rev_page );
				$pages[$change->rev_page] = [ $publishedRevision, $this->subjectsOf( $publishedRevision ) ];
				$reads++;
			}

			[ $published, $pageSubjects ] = $pages[$change->rev_page];

			if ( $published === null || $pageSubjects === null || $this->isAfter( $change, $published )
				|| array_diff( $pageSubjects->getAllSubjects()->getIdsAsTextArray(), array_keys( $edits ) ) === []
			) {
				continue;
			}

			$reads++;
			$publishedById = $pageSubjects->getAllSubjects();

			foreach ( $this->subjectsChangedBy( (int)$change->rev_id, $published ) as $changed ) {
				// Gone from the page since: deleted, or moved to a page whose own revision lists it.
				$subject = $publishedById->getSubject( $changed->id );

				if ( $subject !== null ) {
					$edits[$subject->id->text] ??= [ $subject, $pageSubjects, $published, $change ];
				}
			}
		}

		return array_map(
			fn ( array $edit ): SubjectSummary => $this->newSummary( ...$edit ),
			array_slice( array_values( $edits ), 0, $limit )
		);
	}

	/**
	 * @return iterable<stdClass> Newest first. A revision changed the Subject slot where its slot_origin is set and
	 *   differs from its parent's: a rollback or an undo restores earlier content under that content's own origin.
	 */
	private function newestRevisions(): iterable {
		try {
			$roleId = $this->slotRoleStore->getId( MediaWikiSubjectRepository::SLOT_NAME );
		} catch ( NameTableAccessException ) {
			// A wiki that never stored a Subject has no such slot role.
			return [];
		}

		/** @var iterable<stdClass> $rows */
		$rows = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( [
				'rev_id', 'rev_page', 'rev_timestamp',
				'slot_origin' => 'own_slot.slot_origin', 'parent_slot_origin' => 'parent_slot.slot_origin',
			] )
			->from( 'revision' )
			// Joined rather than required, so that the limit caps the revisions looked through, not the ones found.
			->leftJoin( 'slots', 'own_slot', [ 'own_slot.slot_revision_id = rev_id', 'own_slot.slot_role_id' => $roleId ] )
			->leftJoin( 'slots', 'parent_slot', [ 'parent_slot.slot_revision_id = rev_parent_id', 'parent_slot.slot_role_id' => $roleId ] )
			->orderBy( [ 'rev_timestamp', 'rev_id' ], SelectQueryBuilder::SORT_DESC )
			->limit( $this->revisionBound )
			->caller( __METHOD__ )
			->fetchResultSet();

		return $rows;
	}

	private function publishedRevision( int $pageId ): ?RevisionRecord {
		$latest = $this->revisionLookup->getRevisionByPageId( $pageId );

		if ( $latest === null || !$this->readAuthorizer->authorizeReadByPageTitle( Title::newFromPageIdentity( $latest->getPage() ) ) ) {
			return null;
		}

		return $this->revisionPolicy->publishedRevision( $latest );
	}

	private function isAfter( stdClass $change, RevisionRecord $published ): bool {
		return [ MWTimestamp::convert( TS_MW, $change->rev_timestamp ), (int)$change->rev_id ]
			> [ $published->getTimestamp(), $published->getId() ];
	}

	/** @return Subject[] */
	private function subjectsChangedBy( int $revisionId, RevisionRecord $published ): array {
		$revision = $revisionId === $published->getId() ? $published : $this->revisionLookup->getRevisionById( $revisionId );
		$after = $this->subjectsOf( $revision );

		if ( $revision === null || $after === null ) {
			return [];
		}

		$before = $this->subjectsOf( $this->revisionLookup->getPreviousRevision( $revision ) )?->getAllSubjects();

		return array_filter(
			$after->getAllSubjects()->asArray(),
			static fn ( Subject $subject ): bool =>
				self::dataOf( $before?->getSubject( $subject->id ) ) !== self::dataOf( $subject )
		);
	}

	/**
	 * Null for a slot that is absent, does not deserialize, or is hidden from the public, so hidden content never
	 * counts, not even as what a later edit is compared with.
	 */
	private function subjectsOf( ?RevisionRecord $revision ): ?PageSubjects {
		$content = $revision === null ? null : SubjectSlotReader::read( $revision );

		try {
			return $content?->getPageSubjects();
		} catch ( Exception | TypeError ) {
			// A Subject or Statement stored as something other than an object fails on a parameter type.
			return null;
		}
	}

	/**
	 * Compared rather than the stored bytes, which an import or Special:NeoJson writes in a form of its own.
	 * @return ?array<int, mixed>
	 */
	private static function dataOf( ?Subject $subject ): ?array {
		if ( $subject === null ) {
			return null;
		}

		$statements = array_map(
			static fn ( Statement $statement ): array => [ $statement->getPropertyType(), $statement->getValue()->toScalars() ],
			$subject->getStatements()->asArray()
		);
		ksort( $statements, SORT_STRING );

		return [ $subject->getLabel()?->text, $subject->getSchemaReference()->toJson(), $statements ];
	}

	private function newSummary( Subject $subject, PageSubjects $pageSubjects, RevisionRecord $published, stdClass $change ): SubjectSummary {
		$pageTitle = $this->titleFormatter->getPrefixedText( $published->getPage() );
		$name = SubjectDisplayName::labelOrPageName( $subject, $pageSubjects, $pageTitle );
		$schemaName = $subject->getSchemaName()->getText();

		return new SubjectSummary(
			subjectId: $subject->id->text,
			displayName: $name ?? $schemaName,
			displayNameIsGenerated: $name === null,
			schemaName: $schemaName,
			pageId: (int)$change->rev_page,
			pageTitle: $pageTitle,
			lastEdited: (string)MWTimestamp::convert( TS_ISO_8601, $change->rev_timestamp ),
			hostSubject: $name === null ? $this->hostSubjectBuilder->build( $subject, $pageSubjects, $pageTitle ) : null,
		);
	}

}
