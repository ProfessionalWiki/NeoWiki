const { mount } = require( '@vue/test-utils' );
const App = require( '../../../resources/ext.neowiki.addButton/components/App.vue' );
const { createPinia, setActivePinia } = require( 'pinia' );

describe( 'Add button', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'renders text', () => {
		const wrapper = mount( App, {
			global: {
				plugins: [ createPinia() ]
			}
		} );
		expect( wrapper.text() ).toContain( 'neowiki-add-button' );
	} );
} );
