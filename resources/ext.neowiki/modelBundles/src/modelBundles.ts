import { createMwApp } from 'vue';
import { mountNeoWikiApp, NeoWikiExtension, NeoWikiServices, onUiStart } from 'ext.neowiki';
import DataModelPage from './components/DataModelPage.vue';
import { ModelBundleServices } from './ModelBundleServices.ts';

onUiStart( () => {
	const dataModelPage = document.getElementById( 'ext-neowiki-data-model' );

	if ( dataModelPage !== null ) {
		const app = createMwApp( DataModelPage );
		app.use( NeoWikiExtension.getInstance().getPinia() );
		NeoWikiServices.registerServices( app );
		ModelBundleServices.registerServices( app );
		mountNeoWikiApp( app, dataModelPage );
	}
} );
