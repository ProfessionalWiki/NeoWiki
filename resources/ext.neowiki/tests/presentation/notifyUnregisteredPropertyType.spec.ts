import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
	notifyUnregisteredPropertyType,
	resetUnregisteredPropertyTypeNotifications,
} from '@/presentation/notifyUnregisteredPropertyType';

function stubMw( editable: boolean ): void {
	vi.stubGlobal( 'mw', {
		config: { get: vi.fn( ( key: string ) => key === 'wgIsProbablyEditable' ? editable : undefined ) },
		msg: vi.fn( ( key: string, ...params: string[] ) => `${ key }:${ params.join( ',' ) }` ),
		notify: vi.fn(),
	} );
}

describe( 'notifyUnregisteredPropertyType', () => {

	beforeEach( () => {
		resetUnregisteredPropertyTypeNotifications();
		stubMw( true );
	} );

	it( 'shows a single warning notification carrying the type name', () => {
		notifyUnregisteredPropertyType( 'color' );

		expect( mw.notify ).toHaveBeenCalledTimes( 1 );
		expect( mw.notify ).toHaveBeenCalledWith(
			'neowiki-property-type-unregistered-notification:color',
			{ type: 'warn' },
		);
	} );

	it( 'notifies only once for repeated encounters of the same type', () => {
		notifyUnregisteredPropertyType( 'color' );
		notifyUnregisteredPropertyType( 'color' );
		notifyUnregisteredPropertyType( 'color' );

		expect( mw.notify ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'notifies separately for each distinct unregistered type', () => {
		notifyUnregisteredPropertyType( 'color' );
		notifyUnregisteredPropertyType( 'rating' );

		expect( mw.notify ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does not notify readers who cannot edit the page', () => {
		stubMw( false );

		notifyUnregisteredPropertyType( 'color' );

		expect( mw.notify ).not.toHaveBeenCalled();
	} );

} );
