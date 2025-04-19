import { describe, expect, it } from 'vitest';
import { PropertyTypeRegistry } from '@neo/domain/PropertyType';
import { TextType } from '../valueFormats/Text';
import { NumberType } from '../valueFormats/Number';

describe( 'PropertyTypeRegistry', () => {

	const emptyRegistry = new PropertyTypeRegistry();

	function newRegistryWithTypes(): PropertyTypeRegistry {
		const registry = new PropertyTypeRegistry();
		registry.registerType( new TextType() );
		registry.registerType( new NumberType() );
		return registry;
	}

	describe( 'getType', () => {

		it( 'throws error for unknown types', () => {
			expect( () => emptyRegistry.getType( 'unknown' ) ).toThrow( 'Unknown property type: unknown' );
			expect( () => newRegistryWithTypes().getType( 'unknown' ) ).toThrow( 'Unknown property type: unknown' );
		} );

		it( 'returns known types', () => {
			expect( newRegistryWithTypes().getType( TextType.typeName ) ).toBeInstanceOf( TextType );
			expect( newRegistryWithTypes().getType( NumberType.typeName ) ).toBeInstanceOf( NumberType );
		} );

	} );

	describe( 'getTypeNames', () => {

		it( 'returns empty array for empty registry', () => {
			expect( emptyRegistry.getTypeNames() ).toEqual( [] );
		} );

		it( 'returns names of all registered types', () => {
			expect( newRegistryWithTypes().getTypeNames() ).toEqual( [ TextType.typeName, NumberType.typeName ] );
		} );

	} );

} );
