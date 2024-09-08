const { mount } = require( '@vue/test-utils' );
const App = require( '../../../resources/ext.neowiki.infobox/components/App.vue' );

describe( 'Infobox', () => {
	it( 'renders text', () => {
		const wrapper = mount( App );
		expect( wrapper.text() ).toContain( 'infobox' );
	} );
} );
