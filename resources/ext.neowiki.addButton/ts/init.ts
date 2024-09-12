// init.ts
import Vue from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import App from './components/App.vue';
import { useNeoWikiStore } from './store.ts';

( function () {
	// Create the Pinia instance
	const pinia = createPinia();

	// Set the active Pinia instance
	setActivePinia( pinia );

	// Initialize the store
	const store = useNeoWikiStore();

	const app = (Vue as any).createMwApp(App);

	// Attach the store to the app
	app.provide( 'store', store );

	// Mount the app
	app.mount( '#neowiki-add-button' );
}() );
