<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\EntryPoints;

use InvalidArgumentException;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
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
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\SchemaContentValidator;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\MediaWiki\Presentation\JsonSchemaErrorFormatter;
use Skin;
use SkinTemplate;
use Status;
use WikiPage;

class NeoWikiHooks {

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		if ( self::isContentPage( $out ) ) {
			self::handleContentPage( $out );
		}
	}

	private static function isContentPage( OutputPage $out ): bool {
		return $out->isArticle()
			&& MediaWikiServices::getInstance()->getNamespaceInfo()->isContent( $out->getTitle()->getNamespace() );
	}

	private static function handleContentPage( OutputPage $out ): void {
		if ( NeoWikiExtension::getInstance()->isDevelopmentUIEnabled() ) {
			$out->addHTML( NeoWikiExtension::getInstance()->getFactBox()->htmlFor( $out->getTitle() ) );
		}

		$out->addModules( 'ext.neowiki' );
		$out->addModuleStyles( 'ext.neowiki.styles' );
		$out->addHtml( '<div id="neowiki"></div>' );

		self::addCreateSubjectButton( $out );
		self::injectMainSubject( $out );
	}

	private static function addCreateSubjectButton( OutputPage $out ): void {
		if ( !NeoWikiExtension::getInstance()->newSubjectAuthorizer( $out->getAuthority() )->canCreateMainSubject() ) {
			return;
		}

		if ( !self::pageIsLatestRevision( $out ) ) {
			return;
		}

		if ( self::pageHasSubjects( $out->getTitle() ) ) {
			return;
		}

		$out->setIndicators( [
			'neowiki-create-button' => '',
		] );
	}

	private static function pageIsLatestRevision( OutputPage $out ): bool {
		return $out->getRevisionId() === $out->getTitle()->getLatestRevID();
	}

	private static function pageHasSubjects( Title $title ): bool {
		return NeoWikiExtension::getInstance()->newSubjectContentRepository()
			->getSubjectContentByPageTitle( $title )
			?->hasSubjects() === true;
	}

	private static function injectMainSubject( OutputPage $out ): void {
		$html = $out->getHTML();
		$out->clearHTML();
		$out->addHTML( self::getMainSubjectHtml( $out ) );
		$out->addHTML( $html );
	}

	private static function getMainSubjectHtml( OutputPage $out ): string {
		$subject = NeoWikiExtension::getInstance()->newSubjectContentRepository()
			->getSubjectContentByPageTitle( $out->getTitle() )
			?->getPageSubjects()->getMainSubject();

		if ( $subject === null ) {
			return '';
		}

		return Html::element(
			'div',
			[
				'class' => 'neowiki-infobox',
				'data-subject-id' => $subject->getId()->text,
			]
		);
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
		NeoWikiExtension::getInstance()->getStoreContentUC()->onRevisionCreated( $revision, $user );
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

	public static function onContentModelCanBeUsedOn( string $modelId, Title $title, bool &$ok ): void {
		if ( $title->getNamespace() === NeoWikiExtension::NS_SCHEMA ) {
			$ok = $modelId === SchemaContent::CONTENT_MODEL_ID;
		}
	}

	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ) {
		if ( !self::isSchemaPage( $skinTemplate->getOutput() ) || !self::userCanEditCurrentPage( $skinTemplate ) ) {
			return;
		}

		// TODO: Rearrange link order.
		$links['views']['edit-schema'] = [
			'class' => ( $skinTemplate->getRequest()->getVal( 'action' ) == 'edit-schema' ) ? 'selected' : '',
			'href' => $skinTemplate->getTitle()->getLocalUrl( 'action=edit-schema' ),
			'text' => $skinTemplate->msg( 'neowiki-edit-with-form' )->text(),
		];
	}

	private static function isSchemaPage( OutputPage $out ): bool {
		return $out->getTitle()->getNamespace() === NeoWikiExtension::NS_SCHEMA;
	}

	private static function userCanEditCurrentPage( SkinTemplate $skinTemplate ): bool {
		return MediaWikiServices::getInstance()->getPermissionManager()->userCan(
			'edit',
			$skinTemplate->getUser(),
			$skinTemplate->getTitle()
		);
	}

}
