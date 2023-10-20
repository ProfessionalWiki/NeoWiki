<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use DatabaseUpdater;
use EditPage;
use Html;
use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\User\UserIdentity;
use OutputPage;
use Parser;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\BlocksContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\CypherContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaContentValidator;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Presentation\JsonSchemaErrorFormatter;
use Skin;
use Title;
use WikiPage;

class MediaWikiHooks {

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

				$registry->defineRoleWithModel(
					role: BlocksContent::SLOT_NAME,
					model: BlocksContent::CONTENT_MODEL_ID,
					layout: [ 'display' => 'none' ]
				);
			}
		);
	}

	public static function onContentHandlerDefaultModelFor( Title $title, ?string &$model ): void {
		if (
			NeoWikiExtension::getInstance()->isDevelopmentUIEnabled()
			&& str_ends_with( $title->getText(), '.query' )
		) {
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
		NeoWikiExtension::getInstance()->getStoreContentUC()->onRevisionCreated( $revision, $wikiPage, $user );
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

	public static function onParserFirstCallInit( Parser $parser ): void {
		$parser->setFunctionHook(
			'infobox',
			NeoWikiExtension::getInstance()->newInfoboxFunction()->handleParserFunctionCall( ... )
		);

		$parser->setFunctionHook(
			'table',
			NeoWikiExtension::getInstance()->newTableFunction()->handleParserFunctionCall( ... )
		);

		$parser->setFunctionHook(
			'cypher',
			NeoWikiExtension::getInstance()->newCypherFunction()->handleParserFunctionCall( ... )
		);
	}

	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ): void {
		$extraLibraries['NeoWiki'] = NeoWikiLua::class;
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		if ( !$out->isArticle() ) {
			return;
		}

		if ( $out->getWikiPage()->getNamespace() === NS_MAIN ) { // TODO: this check is too restrictive. What would be better?
			if ( NeoWikiExtension::getInstance()->isDevelopmentUIEnabled() ) {
				self::addSubjectEditor( $out );
			}

			$content = NeoWikiExtension::getInstance()->getPageContentFetcher()->getPageContent(
				pageTitle: $out->getTitle(),
				authority: $out->getUser(),
				slotName: BlocksContent::SLOT_NAME
			);

			if ( $content instanceof BlocksContent && NeoWikiExtension::getInstance()->isDevelopmentUIEnabled() ) {
				self::addBlocks( $out, $content );
			}
		}

		if ( $out->getWikiPage()->getNamespace() === NeoWikiExtension::NS_SCHEMA ) {
			self::addCreateSubjectButton( $out );
		}
	}

	private static function addBlocks( OutputPage $out, BlocksContent $content ): void {
		$out->addModules( [ 'ext.neowiki.page-blocks' ] );
		$out->addScript( Html::inlineScript(
			<<<JS
window.wikiPageBlocks = {$content->getText()};
JS
		) );
	}

	private static function addSubjectEditor( OutputPage $out ): void {
		$out->addHTML( NeoWikiExtension::getInstance()->getFactBox()->htmlFor( $out->getTitle() ) );
	}

	private static function addCreateSubjectButton( OutputPage $out ): void {
		$out->enableOOUI();
		$out->addModules( [ 'ext.neowiki.table-editor' ] );
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

	public static function onMakeGlobalVariablesScript( array &$vars, OutputPage $out ): void {
		if ( $out->canUseWikiPage() && $out->getWikiPage()->canExist() ) {
			$vars['NeoWiki'] = NeoWikiExtension::getInstance()->config->getExternalReadOnlyNeo4jConfig();
		}
	}

	public static function onSpecialPageInitList( array &$specialPages ): void {
		if ( !NeoWikiExtension::getInstance()->isDevelopmentUIEnabled() ) {
			unset( $specialPages['NeoJson'] );
		}
	}

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
		if ( !is_string( MediaWikiServices::getInstance()->getMainConfig()->get( 'NeoWikiNeo4jExternalReadUrl' ) ) ) {
			return; // The extension has not been initialized yet
		}

		NeoWikiExtension::getInstance()->newNeo4jConstraintUpdater()->createDefaultConstraints();
	}

}
