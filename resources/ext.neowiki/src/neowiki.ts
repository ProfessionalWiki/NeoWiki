import { createMwApp } from 'vue';
import { createPinia } from 'pinia';
import '@/assets/scss/global.scss';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { CdxTooltip } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import EditSchemaAction from '@/components/SchemaEditor/EditSchemaAction.vue';

const automaticInfobox = document.querySelector( '#neowiki' );
if ( automaticInfobox !== null ) {
	const app = createMwApp( NeoWikiApp ).directive( 'tooltip', CdxTooltip );
	app.use( createPinia() );
	NeoWikiServices.registerServices( app );
	app.mount( automaticInfobox );
}

const editSchema = document.querySelector( '#ext-neowiki-edit-schema' );
if ( editSchema !== null ) {
	const app = createMwApp(
		EditSchemaAction,
		{ schemaName: editSchema.getAttribute( 'data-schema-name' ) }
	);
	app.use( createPinia() );
	NeoWikiServices.registerServices( app );
	app.mount( editSchema );
}
