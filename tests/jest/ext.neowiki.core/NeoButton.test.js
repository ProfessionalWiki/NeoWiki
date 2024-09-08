const { shallowMount } = require( '@vue/test-utils' );
const NeoMessage = require( '../../../resources/ext.neowiki.core/components/NeoMessage.vue' );

describe( 'Neo Message', () => {
	it( 'renders text', () => {
		const wrapper = shallowMount( NeoMessage, {
			propsData: {
				message: 'test text'
			}
		} );
		expect( wrapper.text() ).toContain( 'test text' );
	} );
} );
