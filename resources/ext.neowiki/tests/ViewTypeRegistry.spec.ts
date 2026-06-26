import { describe, expect, it } from 'vitest';
import { watchEffect } from 'vue';
import { ViewTypeRegistry } from '@/ViewTypeRegistry.ts';
import type { Component } from 'vue';

describe( 'ViewTypeRegistry', () => {

	describe( 'getComponent', () => {

		it( 'returns the component for a registered type', () => {
			const registry = new ViewTypeRegistry();
			const infoboxComponent = { name: 'InfoboxWrong' } as unknown as Component;
			const tableComponent = { name: 'TableRight' } as unknown as Component;
			const factboxComponent = { name: 'FactboxWrong' } as unknown as Component;

			registry.registerType( 'infobox', infoboxComponent );
			registry.registerType( 'table', tableComponent );
			registry.registerType( 'factbox', factboxComponent );

			expect( registry.getComponent( 'table' ) ).toBe( tableComponent );
		} );

		it( 'throws for an unregistered type', () => {
			const registry = new ViewTypeRegistry();

			expect( () => registry.getComponent( 'unknown' ) )
				.toThrow( 'Unknown view type: unknown' );
		} );

	} );

	describe( 'hasType', () => {

		it( 'returns true for a registered type', () => {
			const registry = new ViewTypeRegistry();
			registry.registerType( 'infobox', {} as Component );

			expect( registry.hasType( 'infobox' ) ).toBe( true );
		} );

		it( 'returns false for an unregistered type', () => {
			const registry = new ViewTypeRegistry();

			expect( registry.hasType( 'infobox' ) ).toBe( false );
		} );

	} );

	describe( 'reactivity', () => {

		it( 'notifies a consumer that already read the registry when a type is registered later', () => {
			const registry = new ViewTypeRegistry();
			const observed: boolean[] = [];

			const stop = watchEffect( () => {
				observed.push( registry.hasType( 'card' ) );
			}, { flush: 'sync' } );

			registry.registerType( 'card', { name: 'Card' } as unknown as Component );
			stop();

			expect( observed ).toEqual( [ false, true ] );
		} );

	} );

} );
