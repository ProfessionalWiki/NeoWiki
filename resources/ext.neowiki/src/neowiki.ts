import { createMwApp } from 'vue';
import App from '@/App.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useNeoWikiStore } from '@/stores/Store.ts';

const pinia = createPinia();
setActivePinia( pinia );
const store = useNeoWikiStore();

createMwApp( App )
	.provide( 'store', store )
	.mount( '#neowiki' );
