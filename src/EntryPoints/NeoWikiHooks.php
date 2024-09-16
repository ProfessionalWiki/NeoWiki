<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints;

use InvalidArgumentException;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\SchemaContentValidator;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\JsonSchemaErrorFormatter;
use Skin;
use WikiPage;

class NeoWikiHooks {

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		if ( !$out->isArticle() ) {
			return;
		}

		$out->addModules( 'ext.neowiki' );
		$out->addModuleStyles( 'ext.neowiki.styles' );
		$out->addHtml( '<div id="neowiki"></div>' );

		$out->setIndicators( [
			'neowiki-create-button' => '',
		] );

		// TODO: remove examples
		$out->addHtml( '<div class="neowiki-example"></div>' );
		$out->addHtml( '<div class="neowiki-example"></div>' );
		$out->addHtml( '<div class="neowiki-example-manual"></div>' );
	}

	public static function onMediaWikiServices( MediaWikiServices $services ): void {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			static function ( SlotRoleRegistry $registry ): void {
				if ( in_array( MediaWikiSubjectRepository::SLOT_NAME, $registry->getDefinedRoles() ) ) {
					return; // Avoid duplicate slot definition.
				}

				$registry->defineRoleWithModel(
					role: MediaWikiSubjectRepository::SLOT_NAME,
					model: SubjectContent::CONTENT_MODEL_ID,
					layout: [ 'display' => 'none' ]
				);
			}
		);
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
		NeoWikiExtension::getInstance()->getStoreContentUC()->onRevisionCreated( $revision, $wikiPage, $user );
		$wikiPage->doPurge(); // clear cache
	}

	public static function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, ?string $model, ?string $format ): void {
		if ( in_array( $model, [ SubjectContent::CONTENT_MODEL_ID, SchemaContent::CONTENT_MODEL_ID ] ) ) {
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

	public static function onEditFilter( EditPage $editPage, ?string $text, ?string $section, string &$error ): void {
		if ( $editPage->getTitle()->getNamespace() === NeoWikiExtension::NS_SCHEMA ) {
			self::validateSchemaEdit( $editPage, $text, $section, $error );
		}
	}

	private static function validateSchemaEdit( EditPage $editPage, ?string $text, ?string $section, string &$error ): void {
		try {
			new SchemaName( $editPage->getTitle()->getText() );
		} catch ( InvalidArgumentException $exception ) {
			$error = \Html::errorBox(
				$exception->getMessage()
			);
		}

		$contentValidator = SchemaContentValidator::newInstance();

		if ( !$contentValidator->validate( $text ) ) {
			$errors = $contentValidator->getErrors();
			$error = \Html::errorBox(
				wfMessage( 'neowiki-schema-invalid', count( $errors ) )->escaped() .
				JsonSchemaErrorFormatter::format( $errors )
			);
		}
	}

	public static function onSpecialPageInitList( array &$specialPages ): void {
		if ( !NeoWikiExtension::getInstance()->isDevelopmentUIEnabled() ) {
			unset( $specialPages['NeoJson'] );
		}
	}

}
