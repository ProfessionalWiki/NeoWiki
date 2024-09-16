/* eslint-disable @typescript-eslint/no-explicit-any */

import { createMwApp, h } from 'vue';
import { createPinia } from 'pinia';
import NeoExample from '@/NeoExample.vue';
import { useNeoWikiStore } from '@/stores/Store';
import CreateButton from '@/components/CreateButton.vue';

const app = createMwApp( {
	setup() {
		const store = useNeoWikiStore();
		return { store };
	},
	render() {
		// TODO: Example: mount multiple components.
		const examples = document.querySelectorAll( '.neowiki-example' );
		const components = Array.from( examples ).map( ( el, index ) => h( NeoExample, {
			key: index,
			ref: ( instance ) => {
				if ( instance !== null ) {
					el.appendChild( ( instance as any ).$el );
				}
			}
		} ) );

		const createButton = document.getElementById( 'mw-indicator-neowiki-create-button' );
		if ( createButton ) {
			components.push( h( CreateButton, {
				key: 'create-button',
				ref: ( instance ) => {
					if ( instance !== null ) {
						createButton.appendChild( ( instance as any ).$el );
					}
				}
			} ) );
		}

		return components;
	}
} );

app.use( createPinia() );
app.mount( '#neowiki' );
