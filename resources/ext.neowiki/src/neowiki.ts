/* eslint-disable @typescript-eslint/no-explicit-any */

import { createMwApp, h } from 'vue';
import { createPinia } from 'pinia';
import { useNeoWikiStore } from '@/stores/Store';
import AutomaticInfobox from '@/components/AutomaticInfobox.vue';
import CreateSubjectButton from '@/components/CreateSubject/CreateSubjectButton.vue';

const app = createMwApp( {
	setup() {
		const store = useNeoWikiStore();
		return { store };
	},
	render() {
		const infoboxes = document.querySelectorAll( '.neowiki-infobox' );
		const components = Array.from( infoboxes ).map( ( el, index ) => h( AutomaticInfobox, {
			key: index,
			ref: ( instance ) => {
				if ( instance !== null ) {
					el.appendChild( ( instance as any ).$el );
				}
			},
			// TODO: Remove
			title: 'Foo',
			statements: [
				{ property: 'Foo', value: 'Bar' },
				{ property: 'Lorem', value: 'Ipsum' }
			]
		} ) );

		const createButton = document.getElementById( 'mw-indicator-neowiki-create-button' );
		if ( createButton ) {
			components.push( h( CreateSubjectButton, {
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
