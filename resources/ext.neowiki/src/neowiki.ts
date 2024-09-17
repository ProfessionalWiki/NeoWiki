import { createMwApp } from 'vue';
import { createPinia } from 'pinia';
import NeoWikiApp from '@/components/NeoWikiApp.vue';

const app = createMwApp( NeoWikiApp );
app.use( createPinia() );
app.mount( '#neowiki' );
