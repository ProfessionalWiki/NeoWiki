import { describe, expect, it } from 'vitest';
import { ValueFormatRegistry } from '@/editor/domain/ValueFormat';
import { TextFormat } from '../valueFormats/Text';
import { NumberFormat } from '../valueFormats/Number';

describe( 'ValueFormatRegistry', () => {

	const emptyRegistry = new ValueFormatRegistry();

	function newRegistryWithFormats(): ValueFormatRegistry {
		const registry = new ValueFormatRegistry();
		registry.registerFormat( new TextFormat() );
		registry.registerFormat( new NumberFormat() );
		return registry;
	}

	describe( 'getFormat', () => {

		it( 'throws error for unknown formats', () => {
			expect( () => emptyRegistry.getFormat( 'unknown' ) ).toThrow( 'Unknown value format: unknown' );
			expect( () => newRegistryWithFormats().getFormat( 'unknown' ) ).toThrow( 'Unknown value format: unknown' );
		} );

		it( 'returns known formats', () => {
			expect( newRegistryWithFormats().getFormat( TextFormat.formatName ) ).toBeInstanceOf( TextFormat );
			expect( newRegistryWithFormats().getFormat( NumberFormat.formatName ) ).toBeInstanceOf( NumberFormat );
		} );

	} );

	describe( 'getFormatNames', () => {

		it( 'returns empty array for empty registry', () => {
			expect( emptyRegistry.getFormatNames() ).toEqual( [] );
		} );

		it( 'returns names of all registered formats', () => {
			expect( newRegistryWithFormats().getFormatNames() ).toEqual( [ TextFormat.formatName, NumberFormat.formatName ] );
		} );

	} );

} );
