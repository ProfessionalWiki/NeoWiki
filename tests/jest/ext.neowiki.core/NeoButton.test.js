const { shallowMount } = require( '@vue/test-utils' );
const NeoMessage = require( '../../../resources/ext.neowiki.core/components/NeoMessage.vue' );
const { setActivePinia, createPinia } = require( 'pinia' );

describe( 'Neo Message', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	it( 'renders text', () => {
		const wrapper = shallowMount( NeoMessage, {
			propsData: {
				message: 'test text'
			},
			global: {
				plugins: [ createPinia() ]
			}
		} );
		expect( wrapper.text() ).toContain( 'test text' );
	} );
} );
