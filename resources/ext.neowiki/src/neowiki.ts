import { createMwApp } from 'vue';
import App from '@/App.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useNeoWikiStore } from '@/stores/Store.ts';
import { Neo } from 'neo';

const pinia = createPinia();
setActivePinia( pinia );
const store = useNeoWikiStore();

if ( Neo.getInstance().add( 1, 2 ) > 0 ) {
	createMwApp( App )
		.provide( 'store', store )
		.mount( '#neowiki' );
}
