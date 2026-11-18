import { type Component, createMwApp, defineComponent, h, provide } from 'vue';
import { type FrontendRegistrar, mountNeoWikiApp, NeoWikiExtension, NeoWikiServices, onUiStart } from 'ext.neowiki';
import DataModelPage from './components/DataModelPage.vue';
import ModelBundlesNotice from './components/ModelBundlesNotice.vue';
import { ModelBundleServices } from './ModelBundleServices.ts';

/**
 * The component, in an app of NeoWiki's that does not provide this extension's services.
 */
function withModelBundleServices( component: Component ): Component {
	return defineComponent( {
		inheritAttrs: false,
		setup( _props, { attrs } ) {
			Object.entries( ModelBundleServices.getServices() ).forEach( ( [ key, service ] ) => provide( key, service ) );
			return () => h( component, attrs );
		},
	} );
}

mw.hook( 'neowiki.registration' ).add( ( registrar: FrontendRegistrar ) => {
	const notice = withModelBundleServices( ModelBundlesNotice );
	// Set by the page for a user who may install bundles; the notice offers the setup then.
	const setupOffered = mw.config.get( 'wgNeoWikiModelBundlesSetupOffered' ) === true;

	registrar.registerOverviewNotice( notice, setupOffered );
	registrar.registerSchemasNotice( notice, setupOffered );
} );

onUiStart( () => {
	const dataModelPage = document.getElementById( 'ext-neowiki-data-model' );

	if ( dataModelPage !== null ) {
		const app = createMwApp( DataModelPage, { view: dataModelPage.dataset.mwNeowikiView ?? null } );
		app.use( NeoWikiExtension.getInstance().getPinia() );
		NeoWikiServices.registerServices( app );
		ModelBundleServices.registerServices( app );
		mountNeoWikiApp( app, dataModelPage );
	}
} );
