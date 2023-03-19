<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use CommentStoreComment;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\User\UserIdentity;
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
		NeoWikiExtension::getInstance()->getStoreContentUC()->storeContent( $renderedRevision, $user );
	}

	public static function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, ?string $model, ?string $format ): void {
		if ( $model === SubjectContent::CONTENT_MODEL_ID ) {
			$lang = 'json';
		}
	}

}
