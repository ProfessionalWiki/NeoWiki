<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Jobs;

use GenericParameterJob;
use Job;
use JobSpecification;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Reprojects the pages one user is the last editor of, out of band, after a block hid their name or
 * an unblock showed it again (#1246). Out of band because a block changes every page the user last
 * edited at once, where the graph write path handles one page at a time.
 *
 * Nothing is caught: a projection failure fails the job, and the queue runs it again. Reprojecting a
 * page is idempotent, so a retry that redoes pages an earlier attempt already reached costs only the
 * writes.
 */
class RebuildLastEditorPagesJob extends Job implements GenericParameterJob {

	public const string TYPE = 'neowikiRebuildLastEditorPages';

	public function __construct( array $params ) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * Filed as deduplicable: the job carries no state of its own and reads whether the wiki shows the
	 * name when it runs, so a second copy queued while the first is still waiting has nothing to add.
	 */
	public static function newSpecification( UserIdentity $user ): JobSpecification {
		return new JobSpecification(
			self::TYPE,
			[ 'userId' => $user->getId(), 'userName' => $user->getName() ],
			[ 'removeDuplicates' => true ]
		);
	}

	/**
	 * A job whose parameters are not what this version files — one queued by another version, or edited
	 * by hand — names no user, and reprojects nothing rather than guessing at one.
	 */
	public function run(): bool {
		$userId = (int)( $this->params['userId'] ?? 0 );
		$userName = (string)( $this->params['userName'] ?? '' );

		if ( $userId === 0 || $userName === '' ) {
			return true;
		}

		NeoWikiExtension::getInstance()
			->newLastEditorPagesRebuilder()
			->rebuildPagesLastEditedBy( UserIdentityValue::newRegistered( $userId, $userName ) );

		return true;
	}

}
