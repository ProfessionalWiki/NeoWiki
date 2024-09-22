import { createMwApp } from 'vue';
import { createPinia } from 'pinia';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { Neo } from '@neo/Neo.ts';
import { CdxTooltip } from '@wikimedia/codex';

const app = createMwApp( NeoWikiApp ).directive( 'tooltip', CdxTooltip );
app.use( createPinia() );
app.mount( '#neowiki' );

// TODO: this is just to include Neo code in the build. Remove when actually using it.
Neo.getInstance();
