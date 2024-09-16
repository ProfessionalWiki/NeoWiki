/* eslint-disable @typescript-eslint/no-explicit-any */

import { createMwApp, h } from 'vue';
import NeoExample from '@/NeoExample.vue';
import { createPinia, setActivePinia } from 'pinia';
import { useNeoWikiStore } from '@/stores/Store';

const pinia = createPinia();
setActivePinia( pinia );
const store = useNeoWikiStore();

const app = createMwApp( {
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
		} )
		);

		// TODO: Example: mount a single components.
		const manualExample = document.querySelector( '.neowiki-example-manual' );
		if ( manualExample ) {
			components.push( h( NeoExample, {
				key: 'manual',
				ref: ( instance ) => {
					if ( instance !== null ) {
						manualExample.appendChild( ( instance as any ).$el );
					}
				}
			} ) );
		}

		return components;
	}
} );

app.provide( 'store', store );
app.mount( '#neowiki' );
