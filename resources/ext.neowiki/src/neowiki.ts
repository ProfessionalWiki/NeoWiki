import { createMwApp } from 'vue';
import { createPinia } from 'pinia';
import '@/assets/scss/global.scss';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { CdxTooltip } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const app = createMwApp( NeoWikiApp ).directive( 'tooltip', CdxTooltip );
app.use( createPinia() );
NeoWikiServices.registerServices( app );
app.mount( '#neowiki' );
