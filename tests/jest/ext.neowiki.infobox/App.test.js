const { mount } = require( '@vue/test-utils' );
const App = require( '../../../resources/ext.neowiki.infobox/components/App.vue' );
const { setActivePinia, createPinia } = require( 'pinia' );

describe( 'Infobox', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'renders text', () => {
		const wrapper = mount( App, {
			global: {
				plugins: [ createPinia() ]
			}
		} );
		expect( wrapper.text() ).toContain( 'neowiki-infobox' );
	} );
} );
