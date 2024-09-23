import { createMwApp } from 'vue';
import { createPinia } from 'pinia';
import '@/assets/scss/global.scss';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { Neo } from '@neo/Neo.ts';

const app = createMwApp( NeoWikiApp );
app.use( createPinia() );
app.mount( '#neowiki' );

// TODO: this is just to include Neo code in the build. Remove when actually using it.
Neo.getInstance();
