import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UnregisteredTypeValueDisplay from '@/components/Value/UnregisteredTypeValueDisplay.vue';
import { newUnregisteredTypeValue } from '@/domain/Value';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';
import { resetUnregisteredPropertyTypeNotifications } from '@/presentation/notifyUnregisteredPropertyType';

function unregisteredTypeProperty( type: string ): PropertyDefinition {
	return { name: new PropertyName( 'brandColour' ), type, description: '', required: false };
}

function createWrapper( raw: unknown, typeName = 'color' ): ReturnType<typeof mount> {
	return mount( UnregisteredTypeValueDisplay, {
		props: {
			value: newUnregisteredTypeValue( typeName, raw ),
			property: unregisteredTypeProperty( typeName ),
		},
	} );
}

describe( 'UnregisteredTypeValueDisplay', () => {

	beforeEach( () => {
		resetUnregisteredPropertyTypeNotifications();
		vi.stubGlobal( 'mw', {
			config: { get: vi.fn( ( key: string ) => key === 'wgIsProbablyEditable' ? true : undefined ) },
			msg: vi.fn( ( key: string, ...params: string[] ) => `${ key }:${ params.join( ',' ) }` ),
			notify: vi.fn(),
		} );
	} );

	it( 'renders a string raw value as-is', () => {
		const wrapper = createWrapper( '#ff0000' );

		expect( wrapper.text() ).toContain( '#ff0000' );
	} );

	it( 'renders a structured raw value as JSON', () => {
		const wrapper = createWrapper( { hex: '#ff0000' } );

		expect( wrapper.text() ).toContain( '{"hex":"#ff0000"}' );
	} );

	it( 'shows a note naming the unregistered type', () => {
		const wrapper = createWrapper( '#ff0000', 'color' );

		expect( wrapper.text() ).toContain( 'neowiki-property-type-unregistered-note:color' );
	} );

	it( 'warns the editor about the unregistered type on mount', () => {
		createWrapper( '#ff0000', 'color' );

		expect( mw.notify ).toHaveBeenCalledTimes( 1 );
		expect( mw.notify ).toHaveBeenCalledWith(
			'neowiki-property-type-unregistered-notification:color',
			{ type: 'warn' },
		);
	} );

} );
