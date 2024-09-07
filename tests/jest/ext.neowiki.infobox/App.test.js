const { shallowMount } = require( '@vue/test-utils' );
const App = require( '../../../resources/ext.neowiki.infobox/components/App.vue' );

describe( 'Infobox', () => {
	it( 'renders text', () => {
		const wrapper = shallowMount( App );
		expect( wrapper.text() ).toContain( 'infobox' );
	} );
} );
