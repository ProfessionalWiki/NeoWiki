import { createMwApp } from 'vue';
import { createPinia } from 'pinia';
import '@/assets/scss/global.scss';
import NeoWikiApp from '@/components/NeoWikiApp.vue';
import { CdxTooltip } from '@wikimedia/codex';
import { Service } from '@/Service.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

const app = createMwApp( NeoWikiApp ).directive( 'tooltip', CdxTooltip );
app.use( createPinia() );
app.mount( '#neowiki' );
app.provide( Service.ComponentRegistry, NeoWikiExtension.getInstance().getFormatSpecificComponentRegistry() );
