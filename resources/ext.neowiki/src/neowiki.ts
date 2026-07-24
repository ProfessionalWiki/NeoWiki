import { createMwApp } from 'vue';
import type { App } from 'vue';
import type { Pinia } from 'pinia';
// Global, scoped focus-ring override; see the file header for the rationale.
import '@/assets/keyboard-focus.less';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { CdxTooltip } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import SchemaDisplay from '@/components/SchemaDisplay/SchemaDisplay.vue';
import LayoutDisplay from '@/components/LayoutDisplay/LayoutDisplay.vue';
import SchemasPage from '@/components/SchemasPage/SchemasPage.vue';
import LayoutsPage from '@/components/LayoutsPage/LayoutsPage.vue';
import MappingsPage from '@/components/MappingsPage/MappingsPage.vue';
import SubjectsManagerPage from '@/components/SubjectsManager/SubjectsManagerPage.vue';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { SchemaName } from '@/domain/Schema.ts';
import type { LayoutName } from '@/domain/Layout.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { LayoutDeserializer } from '@/persistence/LayoutDeserializer.ts';
import { showPendingNotification } from '@/presentation/PendingNotification.ts';
import { FrontendRegistrar } from '@/presentation/FrontendRegistrar';
import { useSubjectStore } from '@/stores/SubjectStore';

const SUBJECT_CREATOR_TRIGGER_SELECTOR = '[data-mw-neowiki-action="open-subject-creator"]';

export function registerSubjectCreatorClickHandler( pinia: Pinia, signal?: AbortSignal ): void {
	document.addEventListener( 'click', ( event ) => {
		const target = event.target;
		if ( !( target instanceof Element ) ) {
			return;
		}
		const trigger = target.closest( SUBJECT_CREATOR_TRIGGER_SELECTOR );
		if ( trigger === null ) {
			return;
		}
		event.preventDefault();
		useSubjectStore( pinia ).openSubjectCreator();
	}, { signal } );
}

/**
 * Mounts a NeoWiki Vue app, tagging its root element with `ext-neowiki-ui` so
 * the keyboard-only focus override (assets/keyboard-focus.less) applies to our
 * Codex buttons without touching those of MediaWiki core or other extensions.
 * Dialogs teleport out of this root and carry the class on the CdxDialog itself.
 */
function mountNeoWikiApp( app: App, element: Element ): void {
	element.classList.add( 'ext-neowiki-ui' );
	app.mount( element );
}

function fireRegistrationHook(): void {
	const ext = NeoWikiExtension.getInstance();
	mw.hook( 'neowiki.registration' ).fire(
		new FrontendRegistrar(
			ext.getTypeSpecificComponentRegistry(),
			ext.getPropertyTypeRegistry(),
			ext.getViewTypeRegistry(),
		),
	);
}

function initializeNeoWikiApp(): void {
	queueMicrotask( () => {
		const neowikiApp = document.querySelector( '#mw-content-text > #ext-neowiki-app' );

		if ( neowikiApp !== null ) {
			showPendingNotification( 'neowiki-subject-creator-success' );
			showPendingNotification( 'neowiki-managesubjects-delete-success' );

			const showSubjectCreator = ( neowikiApp as HTMLElement ).dataset.mwNeowikiCreateSubject === 'true';
			const pageHasMainSubject = ( neowikiApp as HTMLElement ).dataset.mwNeowikiPageHasMainSubject === 'true';

			const ext = NeoWikiExtension.getInstance();

			const app = createMwApp( NeoWikiApp, {
				showSubjectCreator,
				pageHasMainSubject,
			} ).directive( 'tooltip', CdxTooltip );
			const pinia = ext.getPinia();
			app.use( pinia );
			NeoWikiServices.registerServices( app );
			mountNeoWikiApp( app, neowikiApp );
			registerSubjectCreatorClickHandler( pinia );
		}
	} );
}

function initializeSchemaView(): void {
	queueMicrotask( async () => {
		const viewSchema = document.querySelector( '#ext-neowiki-view-schema' );

		if ( viewSchema !== null ) {
			const ext = NeoWikiExtension.getInstance();

			const revisionId = mw.config.get( 'wgRevisionId' );
			const schemaName = mw.config.get( 'wgTitle' ) as SchemaName;

			const restApiUrl = ext.getMediaWiki().util.wikiScript( 'rest' );
			const response = await ext.newHttpClient().get( `${ restApiUrl }/v1/revision/${ revisionId }` );

			if ( !response.ok ) {
				throw new Error( 'Error fetching schema revision' );
			}

			const data = await response.json();
			const schemaJson = JSON.parse( data.source );

			if ( schemaJson.propertyDefinitions === undefined ) {
				throw new Error( 'Schema propertyDefinitions is undefined' );
			}

			const schema = new SchemaDeserializer().deserialize( schemaName, schemaJson );

			const app = createMwApp( SchemaDisplay, { schema } );
			app.use( ext.getPinia() );
			NeoWikiServices.registerServices( app );
			mountNeoWikiApp( app, viewSchema );
		}
	} );
}

function initializeSchemasPage(): void {
	queueMicrotask( () => {
		const schemasPage = document.getElementById( 'ext-neowiki-schemas' );

		if ( schemasPage !== null ) {
			const ext = NeoWikiExtension.getInstance();

			const app = createMwApp( SchemasPage );
			app.use( ext.getPinia() );
			NeoWikiServices.registerServices( app );
			mountNeoWikiApp( app, schemasPage );
		}
	} );
}

function initializeLayoutView(): void {
	queueMicrotask( async () => {
		const viewLayout = document.querySelector( '#ext-neowiki-view-layout' );

		if ( viewLayout !== null ) {
			const ext = NeoWikiExtension.getInstance();

			const revisionId = mw.config.get( 'wgRevisionId' );
			const layoutName = mw.config.get( 'wgTitle' ) as LayoutName;

			const restApiUrl = ext.getMediaWiki().util.wikiScript( 'rest' );
			const response = await ext.newHttpClient().get( `${ restApiUrl }/v1/revision/${ revisionId }` );

			if ( !response.ok ) {
				throw new Error( 'Error fetching layout revision' );
			}

			const data = await response.json();
			const layoutJson = JSON.parse( data.source );

			const layout = new LayoutDeserializer().deserialize( layoutName, layoutJson );

			const app = createMwApp( LayoutDisplay, { layout } );
			app.use( ext.getPinia() );
			NeoWikiServices.registerServices( app );
			mountNeoWikiApp( app, viewLayout );
		}
	} );
}

function initializeLayoutsPage(): void {
	queueMicrotask( () => {
		const layoutsPage = document.getElementById( 'ext-neowiki-layouts' );

		if ( layoutsPage !== null ) {
			const ext = NeoWikiExtension.getInstance();

			const app = createMwApp( LayoutsPage );
			app.use( ext.getPinia() );
			NeoWikiServices.registerServices( app );
			mountNeoWikiApp( app, layoutsPage );
		}
	} );
}

function initializeMappingsPage(): void {
	queueMicrotask( () => {
		const mappingsPage = document.getElementById( 'ext-neowiki-mappings' );

		if ( mappingsPage !== null ) {
			const ext = NeoWikiExtension.getInstance();

			const app = createMwApp( MappingsPage );
			app.use( ext.getPinia() );
			NeoWikiServices.registerServices( app );
			mountNeoWikiApp( app, mappingsPage );
		}
	} );
}

function initializeSubjectsManagerPage(): void {
	queueMicrotask( () => {
		const subjectsManager = document.getElementById( 'ext-neowiki-manage-subjects' );

		if ( subjectsManager !== null ) {
			const ext = NeoWikiExtension.getInstance();

			const app = createMwApp( SubjectsManagerPage ).directive( 'tooltip', CdxTooltip );
			const pinia = ext.getPinia();
			app.use( pinia );
			NeoWikiServices.registerServices( app );
			mountNeoWikiApp( app, subjectsManager );
			registerSubjectCreatorClickHandler( pinia );
		}
	} );
}

const isTestEnvironment = typeof window !== 'undefined' &&
	( window as unknown as { neoWikiTestMode?: boolean } ).neoWikiTestMode === true;

if ( !isTestEnvironment ) {
	fireRegistrationHook();
	initializeNeoWikiApp();
	initializeSchemaView();
	initializeLayoutView();
	initializeSchemasPage();
	initializeLayoutsPage();
	initializeMappingsPage();
	initializeSubjectsManagerPage();
}
