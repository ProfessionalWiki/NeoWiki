import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UnknownAttributesEditor from '@/components/SchemaEditor/Property/UnknownAttributesEditor.vue';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';

function createWrapper( type = 'color' ): VueWrapper {
	const property: PropertyDefinition = {
		name: new PropertyName( 'brandColour' ),
		type: type,
		description: '',
		required: false,
	};
	return mount( UnknownAttributesEditor, { props: { property } } );
}

describe( 'UnknownAttributesEditor', () => {

	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			msg: vi.fn( ( key: string, ...params: string[] ) => `${ key }:${ params.join( ',' ) }` ),
		} );
	} );

	it( 'shows a note naming the unknown type', () => {
		const wrapper = createWrapper( 'color' );

		expect( wrapper.text() ).toContain( 'neowiki-property-type-unknown-note:color' );
	} );

	it( 'does not emit attribute updates', () => {
		const wrapper = createWrapper();

		expect( wrapper.emitted( 'update:property' ) ).toBeUndefined();
	} );

} );
