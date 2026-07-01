import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
	notifyUnknownPropertyType,
	resetUnknownPropertyTypeNotifications,
} from '@/presentation/notifyUnknownPropertyType';

function stubMw( editable: boolean ): void {
	vi.stubGlobal( 'mw', {
		config: { get: vi.fn( ( key: string ) => key === 'wgIsProbablyEditable' ? editable : undefined ) },
		msg: vi.fn( ( key: string, ...params: string[] ) => `${ key }:${ params.join( ',' ) }` ),
		notify: vi.fn(),
	} );
}

describe( 'notifyUnknownPropertyType', () => {

	beforeEach( () => {
		resetUnknownPropertyTypeNotifications();
		stubMw( true );
	} );

	it( 'shows a single warning notification carrying the type name', () => {
		notifyUnknownPropertyType( 'color' );

		expect( mw.notify ).toHaveBeenCalledTimes( 1 );
		expect( mw.notify ).toHaveBeenCalledWith(
			'neowiki-property-type-unknown-notification:color',
			{ type: 'warn' },
		);
	} );

	it( 'notifies only once for repeated encounters of the same type', () => {
		notifyUnknownPropertyType( 'color' );
		notifyUnknownPropertyType( 'color' );
		notifyUnknownPropertyType( 'color' );

		expect( mw.notify ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'notifies separately for each distinct unknown type', () => {
		notifyUnknownPropertyType( 'color' );
		notifyUnknownPropertyType( 'rating' );

		expect( mw.notify ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does not notify readers who cannot edit the page', () => {
		stubMw( false );

		notifyUnknownPropertyType( 'color' );

		expect( mw.notify ).not.toHaveBeenCalled();
	} );

} );
