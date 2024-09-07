const { shallowMount } = require( '@vue/test-utils' );
const App = require( '../../../resources/ext.neowiki.addButton/components/App.vue' );

describe( 'Add button', () => {
	it( 'renders text', () => {
		const wrapper = shallowMount( App );
		expect( wrapper.text() ).toContain( 'addButton' );
	} );
} );
