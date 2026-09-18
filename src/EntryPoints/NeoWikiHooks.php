<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Exception;
use HtmlArmor;
use ManualLogEntry;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Search\SearchUpdate;
use MediaWiki\Specials\SpecialSearch;
use MediaWiki\Title\ForeignTitle;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDBAccessObject;
use MessageLocalizer;
use NullIndexField;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHit;
use ProfessionalWiki\NeoWiki\Application\SubjectPermissionHints;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigExample;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\LayoutContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\MappingContent;
use ProfessionalWiki\NeoWiki\EntryPoints\Actions\SubjectsAction;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\RebuildLastEditorPagesJob;
use ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\ScribuntoLuaLibrary;
use ProfessionalWiki\NeoWiki\Maintenance\RebuildSubjectPageIndex;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Presentation\PageToolsBuilder;
use ProfessionalWiki\NeoWiki\Presentation\SubjectNameMessage;
use MediaWiki\SpecialPage\SpecialPage;
use SearchEngine;
use SearchIndexField;
use SearchResult;
use Skin;
use SkinTemplate;
use Throwable;
use WikiPage;

class NeoWikiHooks {

	private const SPECIAL_PAGE_CLASS_PREFIX = 'ProfessionalWiki\\NeoWiki\\EntryPoints\\SpecialPages\\';

	private const SEARCH_UPDATE_BEFORE_1_44 = 'MediaWiki\\Deferred\\SearchUpdate';

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		$carriesCreateSubjectButton = self::carriesCreateSubjectButton( $out );

		if ( self::isContentPage( $out ) ) {
			self::handleContentPage( $out, $skin );
		} elseif ( self::isSchemaPage( $out ) && $out->isArticle() ) {
			self::handleSchemaPage( $out, $skin );
		} elseif ( self::isLayoutPage( $out ) && $out->isArticle() ) {
			self::handleLayoutPage( $out, $skin );
		} elseif ( $carriesCreateSubjectButton ) {
			// The button still needs the config the other page kinds load the module with.
			NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $out, $skin );
		}

		if ( $carriesCreateSubjectButton ) {
			NeoWikiExtension::getInstance()->newFrontendModuleLoader()->loadCreateSubjectPageDeniedReason( $out );
		}
	}

	/**
	 * On an article view, only {{#create_subject}} puts the frontend module in the list before any loader has
	 * run. Special pages and actions add it themselves before this hook, so they do not count.
	 */
	private static function carriesCreateSubjectButton( OutputPage $out ): bool {
		return $out->isArticle() && in_array( 'ext.neowiki', $out->getModules(), true );
	}

	private static function isContentPage( OutputPage $out ): bool {
		return $out->isArticle()
			&& MediaWikiServices::getInstance()->getNamespaceInfo()->isContent( $out->getTitle()->getNamespace() );
	}

	private static function handleContentPage( OutputPage $out, Skin $skin ): void {
		self::warnAboutHalfConfiguredNeo4j();

		$extension = NeoWikiExtension::getInstance();
		$permissionHints = $extension->newSubjectPermissionHints( $out->getAuthority() );

		$extension->newFrontendModuleLoader()->loadWithSubjectPermissions(
			$out,
			$skin,
			$permissionHints->canEditSubject( new PageId( $out->getTitle()->getArticleID() ) )
		);
		$out->addHtml( self::getNeoWikiAppHtml( $out, $permissionHints ) );
		self::addRdfAutodiscoveryLinks( $out );

		if ( !$extension->shouldAutoRenderMainSubject() ) {
			return;
		}

		$revisionId = self::pageIsLatestRevision( $out ) ? null : $out->getRevisionId();
		$builder = $extension->newViewHtmlBuilder();

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

	private static function getNeoWikiAppHtml( OutputPage $out, SubjectPermissionHints $permissionHints ): string {
		$attrs = [
			'id' => 'ext-neowiki-app',
		];

		if ( self::shouldShowSubjectCreator( $out, $permissionHints ) ) {
			$attrs['data-mw-neowiki-create-subject'] = 'true';
			$attrs['data-mw-neowiki-page-has-main-subject'] =
				NeoWikiExtension::getInstance()->newPageSubjectsLookup()
					->pageHasMainSubject( new PageId( $out->getTitle()->getArticleID() ) )
						? 'true'
						: 'false';
		}

		return Html::element( 'div', $attrs );
	}

	private static function shouldShowSubjectCreator( OutputPage $out, SubjectPermissionHints $permissionHints ): bool {
		return $permissionHints->canCreateMainSubject( new PageId( $out->getTitle()->getArticleID() ) )
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

	/**
	 * @param array<string, mixed> $defaults
	 * @param array<string, bool> $inCacheKey
	 * @param array<string, callable> $lazyOptions
	 */
	public static function onParserOptionsRegister( array &$defaults, array &$inCacheKey, array &$lazyOptions ): void {
		ParserAuthority::registerAccessClassOption( $defaults, $inCacheKey );
	}

	/**
	 * @param string[] $forOptions
	 */
	public static function onPageRenderingHash( string &$confstr, User $user, array &$forOptions ): void {
		ParserAuthority::appendAccessClassToRenderingHash( $confstr, $user, $forOptions );
	}

	public static function onParserFirstCallInit( Parser $parser ): void {
		NeoWikiExtension::getInstance()->getNeo4jPlugin()?->registerParserFunctions( $parser );
		NeoWikiExtension::getInstance()->getFirstSparqlPlugin()?->registerParserFunctions( $parser );

		$parser->setFunctionHook(
			'view',
			static function ( Parser $parser, string ...$args ): string|array {
				$parserFunction = new ViewParserFunction( NeoWikiExtension::getInstance()->newPageSubjectsLookup() );
				return $parserFunction->handle( $parser, ...$args );
			}
		);

		$parser->setFunctionHook(
			'neowiki_value',
			static function ( Parser $parser, string ...$args ): string|array {
				$parserFunction = new NeoWikiValueParserFunction(
					NeoWikiExtension::getInstance()->newSubjectResolver( ParserAuthority::of( $parser ) )
				);
				return $parserFunction->handle( $parser, ...$args );
			}
		);

		$parser->setFunctionHook(
			'create_subject',
			static function ( Parser $parser, string ...$args ): string|array {
				$parserFunction = new CreateSubjectParserFunction(
					NeoWikiExtension::getInstance()->newPageSubjectsLookup()
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
		NeoWikiExtension::getInstance()->getStoreContentUC()->onRevisionCreated( $revision );
		$wikiPage->doPurge(); // clear cache

		self::updateSearchIndexOfSlotOnlyEdit( $revision );

		if ( self::changedTheContent( $revision ) ) {
			self::rebuildStoresHoldingChangedMapping( $wikiPage->getTitle() );
		}
	}

	/**
	 * Saving in the Subject editor writes the Subject slot and inherits the main one, and MediaWiki
	 * indexes a page for search only when its main slot changed. Such an edit is indexed from here instead.
	 */
	private static function updateSearchIndexOfSlotOnlyEdit( RevisionRecord $revision ): void {
		if ( !self::changedOnlyTheSubjects( $revision ) ) {
			return;
		}

		self::scheduleSearchUpdate( $revision );
	}

	private static function scheduleSearchUpdate( RevisionRecord $revision ): void {
		// MediaWiki 1.44 moved SearchUpdate to MediaWiki\Search without an alias and stopped it being a
		// DeferrableUpdate, so the class this wiki has is named at runtime and run from a callable.
		$searchUpdate = class_exists( SearchUpdate::class ) ? SearchUpdate::class : self::SEARCH_UPDATE_BEFORE_1_44;

		DeferredUpdates::addCallableUpdate(
			static function () use ( $searchUpdate, $revision ): void {
				( new $searchUpdate(
					$revision->getPageId(),
					$revision->getPage(),
					$revision->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getContent()
				) )->doUpdate();
			}
		);
	}

	/**
	 * Indexes the page's current revision when core did not: core indexes a page only for a revision
	 * that wrote the main slot, which a save from the Subject editor inherits. The current revision is
	 * read fresh from the primary database rather than taken from the revision the hook was handed,
	 * which for an undeletion or an import is not necessarily the one that ended up current.
	 */
	private static function scheduleSearchUpdateOfCurrentRevision( PageIdentity $page ): void {
		$revision = MediaWikiServices::getInstance()->getRevisionLookup()
			->getRevisionByPageId( $page->getId(), 0, IDBAccessObject::READ_LATEST );

		if ( $revision === null || !self::slotIsInherited( $revision, SlotRecord::MAIN ) ) {
			return;
		}

		self::scheduleSearchUpdate( $revision );
	}

	/**
	 * The shape of a save from the Subject editor: the Subject slot written, the main one inherited.
	 */
	private static function changedOnlyTheSubjects( RevisionRecord $revision ): bool {
		return self::slotIsInherited( $revision, SlotRecord::MAIN )
			&& !self::slotIsInherited( $revision, MediaWikiSubjectRepository::SLOT_NAME );
	}

	/**
	 * True too for a slot the revision does not have at all.
	 */
	private static function slotIsInherited( RevisionRecord $revision, string $role ): bool {
		return !$revision->hasSlot( $role )
			|| $revision->getSlot( $role, RevisionRecord::RAW )->isInherited();
	}

	/**
	 * Declares the field holding a page's Subject text, for a search engine that takes fields from extensions.
	 *
	 * @param SearchIndexField[] &$fields
	 */
	public static function onSearchIndexFields( array &$fields, SearchEngine $engine ): void {
		$field = $engine->makeSearchFieldMapping(
			NeoWikiExtension::SUBJECT_SEARCH_FIELD,
			SearchIndexField::INDEX_TYPE_TEXT
		);

		if ( $field instanceof NullIndexField ) {
			return;
		}

		$fields[NeoWikiExtension::SUBJECT_SEARCH_FIELD] = $field;
	}

	public static function onSearchDataForIndex2(
		array &$fields,
		ContentHandler $handler,
		WikiPage $page,
		ParserOutput $output,
		SearchEngine $engine,
		RevisionRecord $revision
	): void {
		$fields[NeoWikiExtension::SUBJECT_SEARCH_FIELD] = NeoWikiExtension::getInstance()
			->newSubjectSearchTextLookup()->getSearchTextForRevision( $revision );
	}

	/**
	 * Leads a result row to its Main Subject where the page exists only to hold it.
	 *
	 * @param Title &$title
	 * @param string|HtmlArmor|null &$titleSnippet
	 * @param SearchResult $result
	 * @param string[] $terms
	 * @param SpecialSearch $specialSearch
	 * @param string[] &$query
	 * @param string[] &$attributes
	 */
	public static function onShowSearchHitTitle( &$title, &$titleSnippet, $result, $terms, $specialSearch, &$query, &$attributes ): void {
		// ShowSearchHit is not run for files, so a retargeted file row would keep the thumbnail and
		// description of the page it no longer leads to.
		if ( $title->getNamespace() === NS_FILE ) {
			return;
		}

		$landing = self::subjectSearchHitFor( $specialSearch, $title, $terms )?->landing;

		if ( $landing === null ) {
			return;
		}

		$title = SpecialPage::getTitleFor( 'Subject', $landing->subjectId->text );
		$titleSnippet = SubjectNameMessage::from(
			$specialSearch,
			$landing->subjectName,
			$landing->subjectNameIsGenerated
		)->text();
	}

	/**
	 * Shows what a result row matched in a Subject, which MediaWiki's own highlighter cannot see.
	 *
	 * @param SpecialSearch $searchPage
	 * @param SearchResult $result
	 * @param string[] $terms
	 * @param string &$link
	 * @param string &$redirect
	 * @param string &$section
	 * @param string &$extract
	 * @param string &$score
	 * @param string &$size
	 * @param string &$date
	 * @param string &$related
	 * @param string &$html
	 */
	public static function onShowSearchHit(
		$searchPage, $result, $terms, &$link, &$redirect, &$section, &$extract, &$score, &$size, &$date, &$related, &$html
	): void {
		$title = $result->getTitle();

		if ( $title === null ) {
			return;
		}

		$hit = self::subjectSearchHitFor( $searchPage, $title, $terms );

		if ( $hit === null ) {
			return;
		}

		$htmlBuilder = NeoWikiExtension::getInstance()->newSubjectSearchHitHtmlBuilder( $searchPage );

		if ( $hit->match !== null ) {
			$extract = $htmlBuilder->buildExtract( $hit->match ) . $extract;
		}

		if ( $hit->landing !== null ) {
			// The size is the page's, not the Subject's.
			$size = '';
		}
	}

	/**
	 * @param string[] $terms
	 */
	private static function subjectSearchHitFor( SpecialSearch $searchPage, Title $title, array $terms ): ?SubjectSearchHit {
		return NeoWikiExtension::getInstance()->getSubjectSearchHitLookup()->forRow(
			$searchPage->getAuthority(),
			$title,
			$terms,
			$searchPage->getRequest()->getText( 'search' )
		);
	}

	/**
	 * Leads the Go button to the Main Subject of a page that exists only to hold it.
	 *
	 * @param string $term
	 * @param Title|null &$title
	 */
	public static function onSearchGetNearMatchComplete( $term, &$title ): void {
		$context = RequestContext::getMain();

		// Also asked by list=search&srwhat=nearmatch, which keeps answering with pages.
		if ( $title === null || !$context->getTitle()?->isSpecial( 'Search' ) ) {
			return;
		}

		$landing = NeoWikiExtension::getInstance()->getSubjectSearchHitLookup()
			->landingForTitle( $context->getAuthority(), $title );

		if ( $landing !== null ) {
			$title = SpecialPage::getTitleFor( 'Subject', $landing->subjectId->text );
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
		NeoWikiExtension::getInstance()->newHookPageRebuilder()->rebuildFromPrimary( $title );

		self::scheduleSearchUpdateOfCurrentRevision( $title );
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

		NeoWikiExtension::getInstance()->newHookPageRebuilder()->rebuildFromPrimary( $title );

		self::scheduleSearchUpdateOfCurrentRevision( $title );

		// Restoring a Mapping page puts a projection back that the stores holding it were rebuilt
		// without, so it changes what their graphs should contain exactly as deleting it did.
		self::rebuildStoresHoldingChangedMapping( $title );
	}

	/**
	 * Reprojects a page whose revision visibility changed. RevisionDelete hides who made a revision, or
	 * shows them again, without creating a revision, so no other entry point fires and the graph would
	 * go on serving a name MediaWiki no longer shows (#1246).
	 *
	 * Which revisions changed is not examined: a page is projected from whichever revision is current, so
	 * reprojecting it agrees with the wiki when that revision was one of them, and rewrites what the graph
	 * already holds when it was not — a redundant write at the frequency RevisionDelete is used. The
	 * current revision is read from the primary database, since the change has only just committed.
	 *
	 * @see ArticleRevisionVisibilitySetHook
	 *
	 * @param int[] $ids
	 * @param array<int, array{oldBits: int, newBits: int}> $visibilityChangeMap
	 */
	public static function onArticleRevisionVisibilitySet( Title $title, array $ids, array $visibilityChangeMap ): void {
		NeoWikiExtension::getInstance()->newHookPageRebuilder()->rebuildFromPrimary( $title );
	}

	/**
	 * Reprojects the pages of a user a block has just hidden. Hiding a user hides the name on every
	 * revision they made, by a direct write that fires no revision hook of its own, so nothing else
	 * would take those names out of the graph (#1246).
	 *
	 * @see BlockIpCompleteHook
	 */
	public static function onBlockIpComplete( DatabaseBlock $block, User $blocker, ?DatabaseBlock $priorBlock ): void {
		self::rebuildPagesOfHiddenUser( $block );
	}

	/**
	 * The counterpart for an unblock, which shows a hidden user's name again.
	 *
	 * This hook runs *before* MediaWiki clears the hidden flags, so the work has to be queued rather
	 * than done here: reading the revisions now would still find the name hidden and change nothing.
	 *
	 * @see UnblockUserCompleteHook
	 */
	public static function onUnblockUserComplete( DatabaseBlock $block, User $unblocker ): void {
		self::rebuildPagesOfHiddenUser( $block );
	}

	/**
	 * Queued rather than done in the request: a block changes every page the user last edited at once,
	 * where the write path this reuses handles one page at a time. Nothing is queued for a block that
	 * does not hide the user, which leaves the graph as it was.
	 *
	 * The queueing is itself deferred, because the unblock hook runs before the flags are cleared and
	 * lazyPush is not lazy under the command line — it pushes there and then, where a runner could
	 * claim the job while the revisions still read as hidden and reproject the name straight back out.
	 */
	private static function rebuildPagesOfHiddenUser( DatabaseBlock $block ): void {
		$user = $block->getTargetUserIdentity();

		if ( !$block->getHideName() || $user === null ) {
			return;
		}

		DeferredUpdates::addCallableUpdate( static function () use ( $user ): void {
			MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush(
				RebuildLastEditorPagesJob::newSpecification( $user )
			);
		} );
	}

	public static function onSpecialPageInitList( array &$specialPages ): void {
		if ( !NeoWikiExtension::getInstance()->isDevelopmentUIEnabled() ) {
			unset( $specialPages['NeoJson'] );
		}

		// Special:SpecialPages lists groups in the order in which each group's first page appears in this
		// list, and core offers no hook for group order, so NeoWiki's pages are moved to the front.
		$neoWikiPages = array_filter(
			$specialPages,
			fn ( $spec ) => is_string( $spec ) && str_starts_with( $spec, self::SPECIAL_PAGE_CLASS_PREFIX )
		);
		$specialPages = $neoWikiPages + $specialPages;
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
		if ( $engine === 'lua' && NeoWikiExtension::getInstance()->isLuaEnabled() ) {
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

		// First, and offered to readers as well: the page to start from when the wiki is unfamiliar.
		array_unshift( $neoWikiTools, self::specialPageLink(
			$skin,
			specialPage: 'NeoWiki',
			message: 'neowiki-sidebar-overview',
			linkId: 't-neowiki-overview'
		) );

		if ( $title->getNamespace() === NeoWikiExtension::NS_SCHEMA ) {
			$neoWikiTools[] = self::specialPageLink(
				$skin,
				specialPage: 'Schemas',
				message: 'neowiki-schema-sidebar-all-schemas',
				linkId: 't-neowiki-schemas'
			);
		}

		if ( $title->getNamespace() === NeoWikiExtension::NS_LAYOUT ) {
			$neoWikiTools[] = self::specialPageLink(
				$skin,
				specialPage: 'Layouts',
				message: 'neowiki-layout-sidebar-all-layouts',
				linkId: 't-neowiki-layouts'
			);
		}

		if ( $title->getNamespace() === NeoWikiExtension::NS_MAPPING ) {
			$neoWikiTools[] = self::specialPageLink(
				$skin,
				specialPage: 'Mappings',
				message: 'neowiki-mapping-sidebar-all-mappings',
				linkId: 't-neowiki-mappings'
			);
		}

		if ( self::shouldLinkCreateSubjectPage( $skin, $neoWikiTools ) ) {
			$neoWikiTools[] = self::specialPageLink(
				$skin,
				specialPage: 'CreateSubject',
				message: 'neowiki-sidebar-create-subject',
				linkId: 't-neowiki-create-subject-page'
			);
		}

		// The section array key is used by MediaWiki as the message key for
		// the section heading, so it must match an existing message name.
		$sidebar['neowiki-page-tools-label'] = $neoWikiTools;
	}

	/**
	 * The page tools open the Subject creator on the page being viewed. Where they do not, the special
	 * page is the way to it, so one Create subject entry shows per page rather than two.
	 *
	 * @param list<array<string, mixed>> $tools
	 */
	private static function shouldLinkCreateSubjectPage( Skin $skin, array $tools ): bool {
		if ( in_array( PageToolsBuilder::CREATE_SUBJECT_ID, array_column( $tools, 'id' ), true ) ) {
			return false;
		}

		return $skin->getAuthority()->isAllowedAll( 'createpage', 'edit' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function specialPageLink(
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
