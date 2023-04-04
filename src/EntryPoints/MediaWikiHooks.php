<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use CommentStoreComment;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\User\UserIdentity;
use OutputPage;
use Parser;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiSubjectRepository;
use ReflectionClass;
use Skin;
use Status;
use Title;
use WikiPage;

class MediaWikiHooks {

	public static function onMediaWikiServices( MediaWikiServices $services ): void {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			static function ( SlotRoleRegistry $registry ) {
				$registry->defineRoleWithModel(
					MediaWikiSubjectRepository::SLOT_NAME,
					SubjectContent::CONTENT_MODEL_ID
				);
			}
		);
	}

	public static function onContentHandlerDefaultModelFor( Title $title, ?string &$model ): void {
		if ( str_ends_with( $title->getText(), '.node' ) ) {
			$model = SubjectContent::CONTENT_MODEL_ID;
		}

		if ( str_ends_with( $title->getText(), '.query' ) ) {
			$model = CypherContent::CONTENT_MODEL_ID;
		}
	}

	/**
	 * @see RevisionFromEditCompleteHook
	 */
	public static function onRevisionFromEditComplete(
		WikiPage $wikiPage,
		RevisionRecord $revision,
		int|bool $originalRevId,
		UserIdentity $user,
		array &$tags
	): void {
		NeoWikiExtension::getInstance()->getStoreContentUC()->onRevisionCreated( $revision );
	}

	public static function onMultiContentSave(
		RenderedRevision $renderedRevision,
		UserIdentity $user,
		CommentStoreComment $summary,
		$flags,
		Status $hookStatus
	): void {
		$title = Title::newFromID( $renderedRevision->getRevision()->getPage()->getId() );

		if ( $title instanceof Title && str_ends_with( $title->getText(), '.node' ) ) {
			$slots = $renderedRevision->getRevision()->getSlots();
			$slotRecord = $slots->getSlot( 'main' );

			if ( $slotRecord->getContent() instanceof SubjectContent ) {
				$reflector = new ReflectionClass( $slots );
				$property = $reflector->getProperty( 'slots' );
				$property->setAccessible( true );

				$slotRecords = $property->getValue( $slots );
				$slotRecords[MediaWikiSubjectRepository::SLOT_NAME] = SlotRecord::newUnsaved( MediaWikiSubjectRepository::SLOT_NAME, $slotRecord->getContent() );
				$property->setValue( $slots, $slotRecords );
			}
		}
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

	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ): void {
		$extraLibraries['NeoWiki'] = NeoWikiLua::class;
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		if ( !$out->isArticle() || $out->getWikiPage()->getNamespace() !== NS_MAIN ) {
			return;
		}

		$out->enableOOUI();
		$out->addModules( [ 'ext.neowiki.subject' ] );
	}

}
