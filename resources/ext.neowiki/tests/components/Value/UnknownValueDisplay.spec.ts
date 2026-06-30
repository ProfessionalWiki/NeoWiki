import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UnknownValueDisplay from '@/components/Value/UnknownValueDisplay.vue';
import { newUnknownValue } from '@/domain/Value';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';
import { resetUnknownPropertyTypeNotifications } from '@/presentation/notifyUnknownPropertyType';

function unknownProperty( type: string ): PropertyDefinition {
	return { name: new PropertyName( 'brandColour' ), type, description: '', required: false };
}

function createWrapper( raw: unknown, typeName = 'color' ): ReturnType<typeof mount> {
	return mount( UnknownValueDisplay, {
		props: {
			value: newUnknownValue( typeName, raw ),
			property: unknownProperty( typeName ),
		},
	} );
}

describe( 'UnknownValueDisplay', () => {

	beforeEach( () => {
		resetUnknownPropertyTypeNotifications();
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

	it( 'shows a note naming the unknown type', () => {
		const wrapper = createWrapper( '#ff0000', 'color' );

		expect( wrapper.text() ).toContain( 'neowiki-property-type-unknown-note:color' );
	} );

	it( 'warns the editor about the unknown type on mount', () => {
		createWrapper( '#ff0000', 'color' );

		expect( mw.notify ).toHaveBeenCalledTimes( 1 );
		expect( mw.notify ).toHaveBeenCalledWith(
			'neowiki-property-type-unknown-notification:color',
			{ type: 'warn' },
		);
	} );

} );
