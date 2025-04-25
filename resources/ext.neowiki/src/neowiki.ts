import { createMwApp } from 'vue';
import { createPinia } from 'pinia';
import '@/assets/scss/global.scss';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { CdxTooltip } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import EditSchemaAction from '@/components/SchemaEditor/EditSchemaAction.vue';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { SchemaName } from '@neo/domain/Schema.ts';

async function initializeAutomaticInfobox(): Promise<void> {
	const automaticInfobox = document.querySelector( '#neowiki' );

	if ( automaticInfobox !== null ) {
		const app = createMwApp( NeoWikiApp ).directive( 'tooltip', CdxTooltip );
		app.use( createPinia() );
		NeoWikiServices.registerServices( app );
		app.mount( automaticInfobox );
	}
}

async function initializeSchemaEditor(): Promise<void> {
	const editSchema = document.querySelector( '#ext-neowiki-edit-schema' );

	if ( editSchema !== null ) {
		const app = createMwApp(
			EditSchemaAction,
			{
				schema: await NeoWikiExtension.getInstance().getSchemaRepository().getSchema(
					editSchema.getAttribute( 'data-schema-name' ) as SchemaName
				)
			}
		);
		app.use( createPinia() );
		NeoWikiServices.registerServices( app );
		app.mount( editSchema );
	}
}

initializeAutomaticInfobox();
initializeSchemaEditor();
