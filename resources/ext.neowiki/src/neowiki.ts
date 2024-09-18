import { createMwApp } from 'vue';
import { createPinia } from 'pinia';
import NeoWikiApp from '@/mediawiki/components/NeoWikiApp.vue';

const app = createMwApp( NeoWikiApp );
app.use( createPinia() );
app.mount( '#neowiki' );
