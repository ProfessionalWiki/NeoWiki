import { describe, expect, it } from 'vitest';
import { PropertyTypeRegistry } from '@neo/domain/PropertyType';
import { TextFormat } from '../valueFormats/Text';
import { NumberFormat } from '../valueFormats/Number';

describe( 'PropertyTypeRegistry', () => {

	const emptyRegistry = new PropertyTypeRegistry();

	function newRegistryWithTypes(): PropertyTypeRegistry {
		const registry = new PropertyTypeRegistry();
		registry.registerType( new TextFormat() );
		registry.registerType( new NumberFormat() );
		return registry;
	}

	describe( 'getType', () => {

		it( 'throws error for unknown types', () => {
			expect( () => emptyRegistry.getType( 'unknown' ) ).toThrow( 'Unknown property type: unknown' );
			expect( () => newRegistryWithTypes().getType( 'unknown' ) ).toThrow( 'Unknown property type: unknown' );
		} );

		it( 'returns known types', () => {
			expect( newRegistryWithTypes().getType( TextFormat.typeName ) ).toBeInstanceOf( TextFormat );
			expect( newRegistryWithTypes().getType( NumberFormat.typeName ) ).toBeInstanceOf( NumberFormat );
		} );

	} );

	describe( 'getTypeNames', () => {

		it( 'returns empty array for empty registry', () => {
			expect( emptyRegistry.getTypeNames() ).toEqual( [] );
		} );

		it( 'returns names of all registered types', () => {
			expect( newRegistryWithTypes().getTypeNames() ).toEqual( [ TextFormat.typeName, NumberFormat.typeName ] );
		} );

	} );

} );
