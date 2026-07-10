import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UnregisteredTypeAttributesEditor from '@/components/SchemaEditor/Property/UnregisteredTypeAttributesEditor.vue';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';

function createWrapper( type = 'color' ): VueWrapper {
	const property: PropertyDefinition = {
		name: new PropertyName( 'brandColour' ),
		type: type,
		description: '',
		required: false,
	};
	return mount( UnregisteredTypeAttributesEditor, { props: { property } } );
}

describe( 'UnregisteredTypeAttributesEditor', () => {

	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			msg: vi.fn( ( key: string, ...params: string[] ) => `${ key }:${ params.join( ',' ) }` ),
		} );
	} );

	it( 'shows a note that the stored settings are preserved', () => {
		const wrapper = createWrapper( 'color' );

		expect( wrapper.text() ).toContain( 'neowiki-property-type-unregistered-attributes-note' );
	} );

	it( 'does not emit attribute updates', () => {
		const wrapper = createWrapper();

		expect( wrapper.emitted( 'update:property' ) ).toBeUndefined();
	} );

} );
