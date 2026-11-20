<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use InvalidArgumentException;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Application\ControlledSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectsToPublish;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\UnreadableSubjectsException;
use StatusValue;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Hooked where MediaWiki saves revisions and deletes or restores pages, so that undo, rollback and every other write
 * path obey the rules for Controlled Subjects. XML import and history merging write without a hook that could refuse.
 */
class ControlledSubjectHooks {

	/**
	 * Compared with the page's current revision rather than the saved one's parent: a null edit hands over the current
	 * revision itself, whose parent is the revision before it.
	 *
	 * @see MultiContentSaveHook
	 */
	public static function onMultiContentSave(
		RenderedRevision $renderedRevision,
		UserIdentity $user,
		CommentStoreComment $summary,
		int $flags,
		Status $status
	): bool {
		if ( self::hasTheRight( $user ) ) {
			return true;
		}

		$revision = $renderedRevision->getRevision();

		return self::allowsWrite( $revision->getPage(), self::currentRevisionOf( $revision->getPage() ), $revision, $user, $status );
	}

	/**
	 * @see PageDeleteHook
	 */
	public static function onPageDelete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		StatusValue $status,
		bool $suppress
	): bool {
		if ( $deleter->isAllowed( ControlledSubjectRules::RIGHT ) ) {
			return true;
		}

		return self::allowsWrite( $page, self::currentRevisionOf( $page ), null, $deleter->getUser(), $status );
	}

	/**
	 * Judged as if the newest restored revision became the page's current one, even when the page keeps a newer one.
	 *
	 * @see PageUndeleteHook
	 *
	 * @param string[] $timestamps
	 * @param int[] $fileVersions
	 */
	public static function onPageUndelete(
		ProperPageIdentity $page,
		Authority $performer,
		string $reason,
		bool $unsuppress,
		array $timestamps,
		array $fileVersions,
		StatusValue $status
	): bool {
		if ( $performer->isAllowed( ControlledSubjectRules::RIGHT ) ) {
			return true;
		}

		return self::allowsWrite(
			$page,
			self::currentRevisionOf( $page ),
			self::newestRevisionToRestore( $page, $timestamps ),
			$performer->getUser(),
			$status
		);
	}

	/**
	 * A null $before is a creation, a null $after a deletion.
	 */
	private static function allowsWrite(
		PageReference $page,
		?RevisionRecord $before,
		?RevisionRecord $after,
		UserIdentity $writer,
		StatusValue $status
	): bool {
		$rules = NeoWikiExtension::getInstance()->newControlledSubjectRules();

		if ( $page->getNamespace() === NeoWikiExtension::NS_SCHEMA
			&& $rules->changesDeclaration( self::schemaOf( $before ), self::schemaOf( $after ) )
		) {
			return self::refuseUnlessSystemUser( $writer, $status, ControlledSubjectRules::DECLARATION_CHANGE_REFUSED );
		}

		if ( !self::changesSubjectSlot( $before, $after ) ) {
			return true;
		}

		$schemaName = $rules->controlledSchemaChangedBy( self::subjectsOf( $before ), self::subjectsOf( $after ) );

		return $schemaName === null
			|| self::refuseUnlessSystemUser( $writer, $status, ControlledSubjectRules::SUBJECT_EDIT_REFUSED, $schemaName->getText() );
	}

	/**
	 * Maintenance scripts, such as the demo data import, write as a system user.
	 */
	private static function refuseUnlessSystemUser( UserIdentity $writer, StatusValue $status, string $message, string ...$params ): bool {
		if ( MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $writer )->isSystemUser() ) {
			return true;
		}

		$status->fatal( $message, ...$params );
		return false;
	}

	private static function hasTheRight( UserIdentity $user ): bool {
		return MediaWikiServices::getInstance()->getPermissionManager()->userHasRight( $user, ControlledSubjectRules::RIGHT );
	}

	private static function schemaOf( ?RevisionRecord $revision ): ?Schema {
		$content = $revision?->getContent( SlotRecord::MAIN, RevisionRecord::RAW );

		if ( !$content instanceof SchemaContent ) {
			return null;
		}

		try {
			return NeoWikiExtension::getInstance()->getPersistenceSchemaDeserializer()->deserialize(
				new SchemaName( $revision->getPage()->getDBkey() ),
				$content->getText()
			);
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * By content: rollback and undo inherit the slot of an older revision, which keeps that revision's origin, so an
	 * inherited slot can still hold other Subjects than its parent.
	 */
	private static function changesSubjectSlot( ?RevisionRecord $before, ?RevisionRecord $after ): bool {
		$slotBefore = self::subjectSlotOf( $before );
		$slotAfter = self::subjectSlotOf( $after );

		if ( $slotBefore === null || $slotAfter === null ) {
			return $slotBefore !== $slotAfter;
		}

		return !$slotAfter->hasSameContent( $slotBefore );
	}

	private static function subjectSlotOf( ?RevisionRecord $revision ): ?SlotRecord {
		return $revision !== null && $revision->hasSlot( MediaWikiSubjectRepository::SLOT_NAME )
			? $revision->getSlot( MediaWikiSubjectRepository::SLOT_NAME, RevisionRecord::RAW )
			: null;
	}

	/**
	 * An unreadable slot holds no Subjects, so writing one over Controlled Subjects removes them.
	 */
	private static function subjectsOf( ?RevisionRecord $revision ): SubjectMap {
		if ( $revision === null ) {
			return new SubjectMap();
		}

		try {
			return SubjectsToPublish::of( $revision )->getAllSubjects();
		} catch ( UnreadableSubjectsException ) {
			return new SubjectMap();
		}
	}

	private static function currentRevisionOf( PageIdentity $page ): ?RevisionRecord {
		return $page->exists()
			? MediaWikiServices::getInstance()->getRevisionLookup()
				->getRevisionByPageId( $page->getId(), 0, IDBAccessObject::READ_LATEST )
			: null;
	}

	/**
	 * @param string[] $timestamps The ones to restore; none restores every archived revision.
	 */
	private static function newestRevisionToRestore( ProperPageIdentity $page, array $timestamps ): ?RevisionRecord {
		$services = MediaWikiServices::getInstance();
		$db = $services->getConnectionProvider()->getPrimaryDatabase();

		$queryBuilder = $services->getRevisionStore()->newArchiveSelectQueryBuilder( $db )
			->joinComment()
			->where( [ 'ar_namespace' => $page->getNamespace(), 'ar_title' => $page->getDBkey() ] )
			->orderBy( [ 'ar_timestamp', 'ar_id' ], SelectQueryBuilder::SORT_DESC )
			->limit( 1 );

		if ( $timestamps !== [] ) {
			$queryBuilder->andWhere( [ 'ar_timestamp' => array_map( $db->timestamp( ... ), $timestamps ) ] );
		}

		$row = $queryBuilder->caller( __METHOD__ )->fetchRow();

		return $row === false ? null : $services->getRevisionStore()->newRevisionFromArchiveRow( $row, 0, $page );
	}

}
