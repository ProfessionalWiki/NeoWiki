import { createMwApp } from 'vue';
import { createPinia } from 'pinia';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { CdxTooltip } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import EditSchemaPage from '@/components/SchemaEditor/EditSchemaPage.vue';
import SchemaDisplay from '@/components/SchemaDisplay/SchemaDisplay.vue';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { SchemaName } from '@/domain/Schema.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';

async function initializeNeoWikiApp(): Promise<void> {
	const neowikiApp = document.querySelector( '#mw-content-text > #ext-neowiki-app' );

	if ( neowikiApp !== null ) {
		const showSubjectCreator = ( neowikiApp as HTMLElement ).dataset.mwExtNeowikiCreateSubject === 'true';

		const app = createMwApp( NeoWikiApp, {
			showSubjectCreator,
		} ).directive( 'tooltip', CdxTooltip );
		app.use( createPinia() );
		NeoWikiServices.registerServices( app );
		app.mount( neowikiApp );
	}
}

async function initializeSchemaEditor(): Promise<void> {
	const editSchema = document.querySelector( '#ext-neowiki-edit-schema' );

	if ( editSchema !== null ) {
		const app = createMwApp(
			EditSchemaPage,
			{
				initialSchema: await NeoWikiExtension.getInstance().getSchemaRepository().getSchema(
					editSchema.getAttribute( 'data-mw-schema-name' ) as SchemaName,
				),
			},
		);
		app.use( createPinia() );
		NeoWikiServices.registerServices( app );
		app.mount( editSchema );
	}
}

async function initializeSchemaView(): Promise<void> {
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
		app.use( createPinia() );
		NeoWikiServices.registerServices( app );
		app.mount( viewSchema );
	}
}

initializeNeoWikiApp();
initializeSchemaEditor();
initializeSchemaView();
