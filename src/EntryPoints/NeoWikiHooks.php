<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Exception;
use ManualLogEntry;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Title\ForeignTitle;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigExample;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\Application\SubjectResolver;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction;
use ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\ScribuntoLuaLibrary;
use ProfessionalWiki\NeoWiki\Maintenance\RebuildSubjectPageIndex;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Presentation\PageToolsBuilder;
use MediaWiki\SpecialPage\SpecialPage;
use Skin;
use SkinTemplate;
use Throwable;
use WikiPage;

class NeoWikiHooks {

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		if ( self::isContentPage( $out ) ) {
			self::handleContentPage( $out, $skin );
		} elseif ( self::isSchemaPage( $out ) && $out->isArticle() ) {
			self::handleSchemaPage( $out, $skin );
		} elseif ( self::isLayoutPage( $out ) && $out->isArticle() ) {
			self::handleLayoutPage( $out, $skin );
		}
	}

	private static function isContentPage( OutputPage $out ): bool {
		return $out->isArticle()
			&& MediaWikiServices::getInstance()->getNamespaceInfo()->isContent( $out->getTitle()->getNamespace() );
	}

	private static function handleContentPage( OutputPage $out, Skin $skin ): void {
		self::warnAboutHalfConfiguredNeo4j();

		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $out, $skin );
		$out->addHtml( self::getNeoWikiAppHtml( $out ) );
		self::addRdfAutodiscoveryLinks( $out );

		if ( !NeoWikiExtension::getInstance()->shouldAutoRenderMainSubject() ) {
			return;
		}

		$revisionId = self::pageIsLatestRevision( $out ) ? null : $out->getRevisionId();
		$builder = NeoWikiExtension::getInstance()->newViewHtmlBuilder();

		$html = $out->getHTML();
		$out->clearHTML();
		$out->addHTML( $builder->mainSubjectHtml( $out->getTitle(), $revisionId ) );
		$out->addHTML( $html );
	}

	/**
	 * A wiki with no graph backend is a supported configuration: Subjects, Schemas, Views and the value
	 * accessors all work without one, and only the query surfaces a backend brings are absent. Half a
	 * Neo4j configuration is not a configuration, though — it reads as a backend that was meant to be
	 * there, so it is still reported.
	 */
	private static function warnAboutHalfConfiguredNeo4j(): void {
		if ( !NeoWikiExtension::getInstance()->config->hasHalfConfiguredNeo4j() ) {
			return;
		}

		LoggerFactory::getInstance( 'NeoWiki' )->warning(
			'NeoWiki: only one of the Neo4j read/write Bolt URLs is configured; both are required. '
			. 'Neo4j is disabled.'
		);
	}

	private static function getNeoWikiAppHtml( OutputPage $out ): string {
		$attrs = [
			'id' => 'ext-neowiki-app',
		];

		if ( self::shouldShowSubjectCreator( $out ) ) {
			$attrs['data-mw-neowiki-create-subject'] = 'true';
			$attrs['data-mw-neowiki-page-has-main-subject'] =
				NeoWikiExtension::getInstance()->newPageSubjectsLookup()
					->pageHasMainSubject( new PageId( $out->getTitle()->getArticleID() ) )
						? 'true'
						: 'false';
		}

		return Html::element( 'div', $attrs );
	}

	private static function shouldShowSubjectCreator( OutputPage $out ): bool {
		return NeoWikiExtension::getInstance()->newSubjectPermissionHints( $out->getAuthority() )
				->canCreateMainSubject( new PageId( $out->getTitle()->getArticleID() ) )
			&& self::pageIsLatestRevision( $out );
	}

	private static function pageIsLatestRevision( OutputPage $out ): bool {
		return $out->getRevisionId() === $out->getTitle()->getLatestRevID();
	}

	/**
	 * Advertises the page's RDF export (native projection) via `<link rel="alternate">` autodiscovery
	 * tags — one Turtle, one TriG — so Linked Data tooling finds the data without reading the API docs.
	 * Every page that exists has an export, holding its page metadata and any Subjects, so every page
	 * advertises one. The exception is a page NeoWiki cannot read, whose export 404s; such a page does
	 * not render at all, so no link is advertised for it either way.
	 * Native only; the per-projection exports are reachable from the Data tab UI.
	 */
	private static function addRdfAutodiscoveryLinks( OutputPage $out ): void {
		$pageId = $out->getTitle()->getArticleID();

		// A view of a title that has no page is still a content-page view, and there is nothing to
		// advertise for a page that does not exist.
		if ( $pageId === 0 ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		$endpoint = $services->getMainConfig()->get( MainConfigNames::RestPath )
			. '/neowiki/v0/page/' . $pageId . '/rdf?projection=' . RdfPageProjector::PROJECTION;
		$urlUtils = $services->getUrlUtils();

		foreach ( [ 'turtle' => 'text/turtle', 'trig' => 'application/trig' ] as $format => $type ) {
			$href = $endpoint . '&format=' . $format;
			$out->addLink( [
				'rel' => 'alternate',
				'type' => $type,
				'href' => $urlUtils->expand( $href, PROTO_CANONICAL ) ?? $href,
			] );
		}
	}

	private static function handleSchemaPage( OutputPage $out, Skin $skin ): void {
		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $out, $skin );

		$out->addHTML(
			Html::element(
				'div',
				[
					'id' => 'ext-neowiki-view-schema',
				]
			)
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
	 * @see LoadExtensionSchemaUpdatesHook
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
		$sqlDirectory = dirname( __DIR__, 2 ) . '/sql/' . $updater->getDB()->getType();

		$updater->addExtensionTable( 'neowiki_rebuild_runs', $sqlDirectory . '/neowiki_rebuild_runs.sql' );
		$updater->addExtensionField(
			'neowiki_rebuild_runs',
			'nwrr_phase',
			$sqlDirectory . '/patch-neowiki_rebuild_runs-nwrr_phase.sql'
		);
		$updater->addExtensionTable( 'neowiki_subject_page', $sqlDirectory . '/neowiki_subject_page.sql' );

		// Between creating the table and filling it, no Subject that existed before resolves to its page,
		// so the backfill runs in the same update.php as the table it fills.
		$updater->addPostDatabaseUpdateMaintenance( RebuildSubjectPageIndex::class );

		$updater->addExtensionUpdate( [ [ self::class, 'initializeGraphDatabases' ] ] );
	}

	/**
	 * A failing backend is reported rather than thrown, and the update carries on: DatabaseUpdater runs
	 * MediaWiki's own schema changes before it fires LoadExtensionSchemaUpdates, so a throw here would
	 * not protect those — it would skip the updates of every extension queued after NeoWiki. The graph
	 * is rebuildable derived state, so it is not worth that.
	 *
	 * The catch is broader than the hook path's deliberate TimeoutException/DBError re-throws, since
	 * there is no user operation here to abort. It deliberately covers building the plugins as well as
	 * initializing them, so that a wiki whose NeoWiki configuration is not readable yet — as during a
	 * fresh install — still finishes its update.
	 */
	public static function initializeGraphDatabases( DatabaseUpdater $updater ): void {
		$updater->output( 'Initializing NeoWiki graph databases...' );

		try {
			NeoWikiExtension::getInstance()->getGraphDatabasePlugin()->initialize();
		} catch ( Exception $e ) {
			self::reportFailedGraphDatabaseInitialization( $updater, $e );
			return;
		}

		$updater->output( "done.\n" );
	}

	private static function reportFailedGraphDatabaseInitialization( DatabaseUpdater $updater, Exception $e ): void {
		$reason = BackendFailureMessage::withoutCredentials( $e->getMessage() );

		$updater->output(
			"failed.\n"
			. '...' . $reason . "\n"
			. "...Re-run update.php once the cause is resolved. The reads the backend serves fail while\n"
			. "...it is unreachable, and the edits made meanwhile are missing from its projection: run\n"
			. "...RebuildGraphDatabases.php to reconcile it.\n"
		);

		// Logged as well, because update.php --quiet discards everything written to the updater, which
		// would otherwise leave a failed initialization with no trace anywhere. The redacted reason
		// rather than the exception, so that what is kept out of the terminal stays out of the log too.
		LoggerFactory::getInstance( 'NeoWiki' )->error(
			'NeoWiki failed to initialize its graph databases during update.php. The wiki itself is updated. '
			. 'Underlying error: ' . $reason
		);
	}

	public static function onParserFirstCallInit( Parser $parser ): void {
		NeoWikiExtension::getInstance()->getNeo4jPlugin()?->registerParserFunctions( $parser );
		NeoWikiExtension::getInstance()->getFirstSparqlPlugin()?->registerParserFunctions( $parser );

		$parser->setFunctionHook(
			'view',
			static function ( Parser $parser, string ...$args ): string|array {
				$parserFunction = new ViewParserFunction(
					NeoWikiExtension::getInstance()->newSubjectContentRepository()
				);
				return $parserFunction->handle( $parser, ...$args );
			}
		);

		$parser->setFunctionHook(
			'neowiki_value',
			static function ( Parser $parser, string ...$args ): string|array {
				$extension = NeoWikiExtension::getInstance();
				$parserFunction = new NeoWikiValueParserFunction(
					new SubjectResolver(
						$extension->newSubjectContentRepository(),
						$extension->getSubjectRepository(),
					)
				);
				return $parserFunction->handle( $parser, ...$args );
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

		if ( self::changedTheContent( $revision ) ) {
			self::rebuildStoresHoldingChangedMapping( $wikiPage->getTitle() );
		}
	}

	/**
	 * Whether this revision says anything new. Protecting or unprotecting a page, or changing when that
	 * expires, inserts a revision carrying the content of the one before it, and this hook fires for
	 * those as for any other. A Mapping page is what a projection is defined by, so reading one of them
	 * as a definition change throws away an in-flight rebuild and reprojects the wiki to reach the same
	 * graph it already had.
	 */
	private static function changedTheContent( RevisionRecord $revision ): bool {
		$parentId = $revision->getParentId();

		if ( $parentId === null || $parentId === 0 ) {
			return true;
		}

		$parent = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $parentId );

		return $parent === null || $parent->getSha1() !== $revision->getSha1();
	}

	/**
	 * A saved or deleted Mapping page changes what every mapped page's graph should contain, and nothing
	 * reprojects those pages. Wikis that have asked for it have the stores holding that projection
	 * rebuilt here; the rest are left to Special:GraphStores, which reports them as stale.
	 *
	 * Deferred past the change's own transaction, because starting a rebuild takes a database lock that
	 * flushes the connection's snapshot, which a transaction with writes pending may not do — and because
	 * an edit must not wait on a lock or a queue to be saved. A wiki that has not asked for this registers
	 * no update at all, so every Mapping edit on it costs nothing.
	 */
	private static function rebuildStoresHoldingChangedMapping( Title $title ): void {
		if ( $title->getNamespace() !== NeoWikiExtension::NS_MAPPING
			|| !NeoWikiExtension::getInstance()->shouldRebuildOnMappingChange() ) {
			return;
		}

		$mappingName = $title->getText();

		// One update per store, so each takes its start lock on a connection with nothing pending. Sharing
		// a round, the second store's lock would find the first store's writes still there and throw.
		foreach (
			NeoWikiExtension::getInstance()->newMappingChangeRebuilder()->storesHoldingProjection( $mappingName )
			as $storeName
		) {
			DeferredUpdates::addCallableUpdate( static function () use ( $storeName, $mappingName ): void {
				self::rebuildStoreHoldingMapping( $storeName, $mappingName );
			} );
		}
	}

	/**
	 * Nothing is thrown out of here: the Mapping has been saved or deleted by the time this runs. A store
	 * whose rebuild could not even be assembled — a backend whose configuration will not resolve — is
	 * reported rather than allowed to take the rest of the deferred work down with it, and
	 * Special:GraphStores still shows the store as stale.
	 */
	private static function rebuildStoreHoldingMapping( string $storeName, string $mappingName ): void {
		try {
			NeoWikiExtension::getInstance()->newMappingChangeRebuilder()->onMappingChanged( $storeName );
		} catch ( Throwable $e ) {
			LoggerFactory::getInstance( 'NeoWiki' )->error(
				'NeoWiki could not rebuild graph store "' . $storeName . '", which holds the projection '
				. 'Mapping page "' . $mappingName . '" defines, so it still holds the old vocabulary. '
				. 'Rebuild it from Special:GraphStores. Underlying error: '
				. BackendFailureMessage::withoutCredentials( $e->getMessage() ),
				[ 'exception' => $e, 'mapping' => $mappingName ]
			);
		}
	}

	/**
	 * Projects imported pages, which RevisionFromEditComplete does not cover, as imported revisions do
	 * not go through the edit path. WikiImporter fires this hook for every import path, once per page and
	 * only once all of the page's revisions are in, so one handler replaces what would otherwise be a
	 * special case per import path. Special:Import and the import API additionally project through
	 * RevisionFromEditComplete, because ImportReporter creates a null revision on top of the import; that
	 * reprojects the same content, making it redundant rather than harmful.
	 *
	 * @see AfterImportPageHook
	 *
	 * @param array<string, mixed> $pageInfo
	 */
	public static function onAfterImportPage(
		Title $title,
		ForeignTitle $foreignTitle,
		int $revCount,
		int $sRevCount,
		array $pageInfo
	): void {
		NeoWikiExtension::getInstance()->newImportPageRebuilder()->rebuildFromPrimary( $title );
	}

	public static function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, ?string $model, ?string $format ): void {
		if ( in_array( $model, [ SubjectContent::CONTENT_MODEL_ID, SchemaContent::CONTENT_MODEL_ID, LayoutContent::CONTENT_MODEL_ID, MappingContent::CONTENT_MODEL_ID ] ) ) {
			$lang = 'json';
		}
	}

	public static function onPageDeleteComplete( ProperPageIdentity $page, Authority $deleter, string $reason, int $pageId, RevisionRecord $deletedRev ): void {
		NeoWikiExtension::getInstance()->getStoreContentUC()->onPageDelete( $pageId );

		$title = Title::newFromPageIdentity( $page );
		self::rebuildStoresHoldingChangedMapping( $title );
	}

	/**
	 * Projects a page restored from the archive, once per undeletion. The per-revision RevisionUndeleted
	 * hook cannot do this: it fires for every restored revision, including ones that do not become
	 * current, and projecting those would write the page as of a stale revision — a restore of old
	 * history onto a page that still exists would replace its Subjects with the ones that revision held.
	 *
	 * The page's current revision is read fresh from the primary database rather than taken from
	 * $restoredRev, which is the last restored revision: a partial restore leaves a newer revision
	 * current.
	 *
	 * @see PageUndeleteCompleteHook
	 *
	 * @param int[] $restoredPageIds
	 */
	public static function onPageUndeleteComplete(
		ProperPageIdentity $page,
		Authority $restorer,
		string $reason,
		RevisionRecord $restoredRev,
		ManualLogEntry $logEntry,
		int $restoredRevisionCount,
		bool $created,
		array $restoredPageIds
	): void {
		$title = Title::newFromPageIdentity( $page );

		NeoWikiExtension::getInstance()->newImportPageRebuilder()->rebuildFromPrimary( $title );

		// Restoring a Mapping page puts a projection back that the stores holding it were rebuilt
		// without, so it changes what their graphs should contain exactly as deleting it did.
		self::rebuildStoresHoldingChangedMapping( $title );
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

		if ( $title->getNamespace() === NeoWikiExtension::NS_LAYOUT ) {
			$ok = $modelId === LayoutContent::CONTENT_MODEL_ID;
		}

		if ( $title->getNamespace() === NeoWikiExtension::NS_MAPPING ) {
			$ok = $modelId === MappingContent::CONTENT_MODEL_ID;
		}
	}

	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ): bool {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.neowiki'] = ScribuntoLuaLibrary::class;
		}

		return true;
	}

	private static function isSchemaPage( OutputPage $out ): bool {
		return $out->getTitle()->getNamespace() === NeoWikiExtension::NS_SCHEMA;
	}

	private static function isLayoutPage( OutputPage $out ): bool {
		return $out->getTitle()->getNamespace() === NeoWikiExtension::NS_LAYOUT;
	}

	public static function onSidebarBeforeOutput( Skin $skin, array &$sidebar ): void {
		$title = $skin->getTitle();

		if ( $title === null || !$title->canExist() ) {
			return;
		}

		$extension = NeoWikiExtension::getInstance();
		$hints = $extension->newSubjectPermissionHints( $skin->getAuthority() );
		$pageId = new PageId( $title->getArticleID() );

		$isContentNamespace = MediaWikiServices::getInstance()
			->getNamespaceInfo()
			->isContent( $title->getNamespace() );

		$neoWikiTools = ( new PageToolsBuilder() )->build(
			title: $title,
			pageId: $title->getArticleID(),
			isContentNamespace: $isContentNamespace,
			canCreateMainSubject: $hints->canCreateMainSubject( $pageId ),
			canEditSubject: $hints->canEditSubject( $pageId ),
			isLatestRevision: self::pageIsLatestRevision( $skin->getOutput() ),
			devUiEnabled: $extension->isDevelopmentUIEnabled(),
			currentAction: MediaWikiServices::getInstance()
				->getActionFactory()
				->getActionName( $skin->getContext() )
		);

		if ( $title->getNamespace() === NeoWikiExtension::NS_SCHEMA ) {
			$neoWikiTools[] = self::allPagesLink(
				$skin,
				specialPage: 'Schemas',
				message: 'neowiki-schema-sidebar-all-schemas',
				linkId: 't-neowiki-schemas'
			);
		}

		if ( $title->getNamespace() === NeoWikiExtension::NS_LAYOUT ) {
			$neoWikiTools[] = self::allPagesLink(
				$skin,
				specialPage: 'Layouts',
				message: 'neowiki-layout-sidebar-all-layouts',
				linkId: 't-neowiki-layouts'
			);
		}

		if ( $title->getNamespace() === NeoWikiExtension::NS_MAPPING ) {
			$neoWikiTools[] = self::allPagesLink(
				$skin,
				specialPage: 'Mappings',
				message: 'neowiki-mapping-sidebar-all-mappings',
				linkId: 't-neowiki-mappings'
			);
		}

		if ( $neoWikiTools !== [] ) {
			// The section array key is used by MediaWiki as the message key for
			// the section heading, so it must match an existing message name.
			$sidebar['neowiki-page-tools-label'] = $neoWikiTools;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function allPagesLink(
		Skin $skin,
		string $specialPage,
		string $message,
		string $linkId
	): array {
		return [
			'text' => $skin->msg( $message )->text(),
			'href' => SpecialPage::getTitleFor( $specialPage )->getLocalURL(),
			'id' => $linkId,
		];
	}

	public static function onSkinTemplateNavigationUniversal( SkinTemplate $sktemplate, array &$links ): void {
		$title = $sktemplate->getTitle();

		if ( !SubjectsAction::isEligibleTitle( $title ) ) {
			return;
		}

		$action = $sktemplate->getRequest()->getRawVal( 'action' );

		$links['views']['neowiki-subjects'] = [
			'class' => $action === SubjectsAction::ACTION_NAME ? 'selected' : false,
			'text' => $sktemplate->msg( 'neowiki-managesubjects-tab' )->text(),
			'href' => $title->getLocalURL( [ 'action' => SubjectsAction::ACTION_NAME ] ),
		];
	}

	private static function handleLayoutPage( OutputPage $out, Skin $skin ): void {
		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $out, $skin );

		$out->addHTML(
			Html::element(
				'div',
				[
					'id' => 'ext-neowiki-view-layout',
				]
			)
		);
	}

	/**
	 * Forces the JSON content model on the on-wiki configuration page, so MediaWiki core requires the
	 * editinterface and editsitejson rights to edit it and enforces JSON syntax on save.
	 */
	public static function onContentHandlerDefaultModelFor( Title $title, ?string &$model ): void {
		if ( NeoWikiExtension::getInstance()->isConfigPage( $title ) ) {
			$model = CONTENT_MODEL_JSON;
		}
	}

	/**
	 * Validates the on-wiki configuration page on save, blocking the edit with precise per-field errors
	 * when the configuration is invalid. A JSON syntax error is left to the JSON content model itself.
	 */
	public static function onEditFilter( EditPage $editor, string $text, string $section, string &$error, string $summary ): void {
		$extension = NeoWikiExtension::getInstance();

		if ( !$extension->isConfigPage( $editor->getTitle() ) ) {
			return;
		}

		$errors = $extension->getConfigValidator()->validate( $text );

		if ( $errors !== [] ) {
			$error = self::formatConfigErrors( $errors, $editor->getContext() );
		}
	}

	/**
	 * @param array[] $errors A list of message specs, each [ messageKey, ...params ].
	 */
	private static function formatConfigErrors( array $errors, MessageLocalizer $localizer ): string {
		$items = '';

		foreach ( $errors as $errorSpec ) {
			$items .= Html::rawElement( 'li', [], $localizer->msg( ...$errorSpec )->escaped() );
		}

		return Html::errorBox(
			$localizer->msg( 'neowiki-config-invalid' )->escaped() . Html::rawElement( 'ul', [], $items )
		);
	}

	/**
	 * Preloads a small valid example when the on-wiki configuration page is created, so an administrator
	 * starts from a working configuration rather than a blank page.
	 */
	public static function onEditFormPreloadText( ?string &$text, Title $title ): void {
		if ( NeoWikiExtension::getInstance()->isConfigPage( $title ) ) {
			$text = ConfigExample::JSON;
		}
	}

	/**
	 * On the on-wiki configuration page, suppresses the default MediaWiki-namespace intro and frames the
	 * JSON editor with a pointer to the documentation and the schema-generated configuration reference.
	 */
	public static function onAlternateEdit( EditPage $editPage ): void {
		$extension = NeoWikiExtension::getInstance();

		if ( !$extension->isConfigPage( $editPage->getTitle() ) ) {
			return;
		}

		$editPage->suppressIntro = true;

		$builder = $extension->newConfigDocumentationBuilder( $editPage->getContext() );
		$editPage->editFormTextTop = $builder->buildPointer();
		$editPage->editFormTextBottom = $builder->buildReference();
	}

	/**
	 * On viewing the on-wiki configuration page, trims the rendered page to the core JSON table and frames
	 * it with the documentation pointer and the schema-generated configuration reference.
	 */
	public static function onConfigPageBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		$title = $out->getTitle();

		if ( $title === null || !NeoWikiExtension::getInstance()->isConfigPage( $title ) ) {
			return;
		}

		$context = $out->getContext();
		$action = MediaWikiServices::getInstance()->getActionFactory()->getActionName( $context );

		// A diff request also resolves to the 'view' action, but renders a comparison the framing
		// would discard, so only a plain page view is reduced to the JSON table and framed.
		if ( $action !== 'view' || $context->getRequest()->getCheck( 'diff' ) ) {
			return;
		}

		$builder = NeoWikiExtension::getInstance()->newConfigDocumentationBuilder( $context );
		$table = self::extractJsonTable( $out->getHTML() );

		$out->clearHTML();
		$out->addHTML( $builder->buildPointer() . $table . $builder->buildReference() );
	}

	/**
	 * Extracts just the core JSON table element. Core wraps it in a <div class="noresize">, so taking
	 * the balanced <table>...</table> rather than everything to the end of the body avoids re-emitting
	 * that wrapper's orphaned closing tag.
	 */
	private static function extractJsonTable( string $html ): string {
		$start = strpos( $html, '<table class="mw-json"' );

		if ( $start === false ) {
			return $html;
		}

		$end = strpos( $html, '</table>', $start );

		if ( $end === false ) {
			return substr( $html, $start );
		}

		return substr( $html, $start, $end - $start + strlen( '</table>' ) );
	}

}
