import { createMwApp } from 'vue';
import type { Pinia } from 'pinia';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { CdxTooltip } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import SchemaDisplay from '@/components/SchemaDisplay/SchemaDisplay.vue';
import LayoutDisplay from '@/components/LayoutDisplay/LayoutDisplay.vue';
import SchemasPage from '@/components/SchemasPage/SchemasPage.vue';
import LayoutsPage from '@/components/LayoutsPage/LayoutsPage.vue';
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

function initializeNeoWikiApp(): void {
	queueMicrotask( () => {
		const neowikiApp = document.querySelector( '#mw-content-text > #ext-neowiki-app' );

		if ( neowikiApp !== null ) {
			showPendingNotification( 'neowiki-subject-creator-success' );
			showPendingNotification( 'neowiki-managesubjects-delete-success' );

			const showSubjectCreator = ( neowikiApp as HTMLElement ).dataset.mwNeowikiCreateSubject === 'true';
			const pageHasMainSubject = ( neowikiApp as HTMLElement ).dataset.mwNeowikiPageHasMainSubject === 'true';

			const ext = NeoWikiExtension.getInstance();
			mw.hook( 'neowiki.registration' ).fire(
				new FrontendRegistrar( ext.getTypeSpecificComponentRegistry(), ext.getPropertyTypeRegistry() ),
			);

			const app = createMwApp( NeoWikiApp, {
				showSubjectCreator,
				pageHasMainSubject,
			} ).directive( 'tooltip', CdxTooltip );
			const pinia = ext.getPinia();
			app.use( pinia );
			NeoWikiServices.registerServices( app );
			app.mount( neowikiApp );
			registerSubjectCreatorClickHandler( pinia );
		}
	} );
}

function initializeSchemaView(): void {
	queueMicrotask( async () => {
		const viewSchema = document.querySelector( '#ext-neowiki-view-schema' );

		if ( viewSchema !== null ) {
			const ext = NeoWikiExtension.getInstance();

			mw.hook( 'neowiki.registration' ).fire(
				new FrontendRegistrar( ext.getTypeSpecificComponentRegistry(), ext.getPropertyTypeRegistry() ),
			);

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
			app.mount( viewSchema );
		}
	} );
}

function initializeSchemasPage(): void {
	queueMicrotask( () => {
		const schemasPage = document.getElementById( 'ext-neowiki-schemas' );

		if ( schemasPage !== null ) {
			const ext = NeoWikiExtension.getInstance();
			mw.hook( 'neowiki.registration' ).fire(
				new FrontendRegistrar( ext.getTypeSpecificComponentRegistry(), ext.getPropertyTypeRegistry() ),
			);

			const app = createMwApp( SchemasPage );
			app.use( ext.getPinia() );
			NeoWikiServices.registerServices( app );
			app.mount( schemasPage );
		}
	} );
}

function initializeLayoutView(): void {
	queueMicrotask( async () => {
		const viewLayout = document.querySelector( '#ext-neowiki-view-layout' );

		if ( viewLayout !== null ) {
			const ext = NeoWikiExtension.getInstance();

			mw.hook( 'neowiki.registration' ).fire(
				new FrontendRegistrar( ext.getTypeSpecificComponentRegistry(), ext.getPropertyTypeRegistry() ),
			);

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
			app.mount( viewLayout );
		}
	} );
}

function initializeLayoutsPage(): void {
	queueMicrotask( () => {
		const layoutsPage = document.getElementById( 'ext-neowiki-layouts' );

		if ( layoutsPage !== null ) {
			const ext = NeoWikiExtension.getInstance();
			mw.hook( 'neowiki.registration' ).fire(
				new FrontendRegistrar( ext.getTypeSpecificComponentRegistry(), ext.getPropertyTypeRegistry() ),
			);

			const app = createMwApp( LayoutsPage );
			app.use( ext.getPinia() );
			NeoWikiServices.registerServices( app );
			app.mount( layoutsPage );
		}
	} );
}

function initializeSubjectsManagerPage(): void {
	queueMicrotask( () => {
		const subjectsManager = document.getElementById( 'ext-neowiki-manage-subjects' );

		if ( subjectsManager !== null ) {
			const ext = NeoWikiExtension.getInstance();

			mw.hook( 'neowiki.registration' ).fire(
				new FrontendRegistrar( ext.getTypeSpecificComponentRegistry(), ext.getPropertyTypeRegistry() ),
			);

			const app = createMwApp( SubjectsManagerPage ).directive( 'tooltip', CdxTooltip );
			const pinia = ext.getPinia();
			app.use( pinia );
			NeoWikiServices.registerServices( app );
			app.mount( subjectsManager );
			registerSubjectCreatorClickHandler( pinia );
		}
	} );
}

const isTestEnvironment = typeof window !== 'undefined' &&
	( window as unknown as { neoWikiTestMode?: boolean } ).neoWikiTestMode === true;

if ( !isTestEnvironment ) {
	initializeNeoWikiApp();
	initializeSchemaView();
	initializeLayoutView();
	initializeSchemasPage();
	initializeLayoutsPage();
	initializeSubjectsManagerPage();
}
