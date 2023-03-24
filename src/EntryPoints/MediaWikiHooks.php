<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use CommentStoreComment;
use MediaWiki\Extension\Network\NetworkFunction\NetworkConfig;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use Parser;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Status;
use Title;

class MediaWikiHooks {

	public static function onContentHandlerDefaultModelFor( Title $title, ?string &$model ): void {
		if ( str_ends_with( $title->getText(), '.node' ) ) {
			$model = SubjectContent::CONTENT_MODEL_ID;
		}

		if ( str_ends_with( $title->getText(), '.query' ) ) {
			$model = CypherContent::CONTENT_MODEL_ID;
		}
	}

	public static function onMultiContentSave(
		RenderedRevision $renderedRevision,
		UserIdentity $user,
		CommentStoreComment $summary,
		$flags,
		Status $hookStatus
	): void {
		NeoWikiExtension::getInstance()->getStoreContentUC()->onPageSave( $renderedRevision );
	}

	public static function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, ?string $model, ?string $format ): void {
		if ( $model === SubjectContent::CONTENT_MODEL_ID ) {
			$lang = 'json';
		}
	}

	public static function onPageDeleteComplete( ProperPageIdentity $page, Authority $deleter, string $reason, int $pageId, RevisionRecord $deletedRev ): void {
		NeoWikiExtension::getInstance()->getStoreContentUC()->onPageDelete( $pageId );
	}

	public static function onRevisionUndeleted( RevisionRecord $restoredRevision, ?int $oldPageId ): void {
		NeoWikiExtension::getInstance()->getStoreContentUC()->onPageUndelete( $restoredRevision );
	}

	public static function onPageMoveComplete(
		LinkTarget $old,
		LinkTarget $new,
		UserIdentity $userIdentity,
		int $pageId,
		int $redirectId,
		string $reason,
		RevisionRecord $revision
	): void {
	}

	public static function onParserFirstCallInit( Parser $parser ): void {
		$parser->setFunctionHook(
			'cypher',
			NeoWikiExtension::getInstance()->newCypherFunction()->handleParserFunctionCall( ... )
		);
	}

}
