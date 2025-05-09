import { describe, expect, it } from 'vitest';
import { TypeSpecificComponentRegistry, TypeSpecificStuff } from '@/TypeSpecificComponentRegistry.ts';

describe( 'TypeSpecificComponentRegistry', () => {

	const registerComponent = (
		registry: TypeSpecificComponentRegistry,
		type: string,
		stuff: Partial<TypeSpecificStuff> = {}
	): void => {
		const mockComponent = {} as any;

		const defaultStuff: TypeSpecificStuff = {
			valueDisplayComponent: mockComponent,
			valueEditor: mockComponent,
			attributesEditor: mockComponent,
			label: 'Default Label',
			icon: 'Default Icon'
		};

		registry.registerType( type, { ...defaultStuff, ...stuff } );
	};

	describe( 'getPropertyTypes', () => {

		it( 'returns an empty array when no types are registered', () => {
			const registry = new TypeSpecificComponentRegistry();

			expect( registry.getPropertyTypes() ).toEqual( [] );
		} );

		it( 'returns all registered types', () => {
			const registry = new TypeSpecificComponentRegistry();

			registerComponent( registry, 'string' );
			registerComponent( registry, 'number' );
			registerComponent( registry, 'boolean' );

			expect( registry.getPropertyTypes() ).toEqual( [ 'string', 'number', 'boolean' ] );
		} );

	} );

	describe( 'getLabelsAndIcons', () => {

		it( 'returns an empty array when no types are registered', () => {
			const registry = new TypeSpecificComponentRegistry();

			const labelsAndIcons = registry.getLabelsAndIcons();

			expect( labelsAndIcons ).toEqual( [] );
		} );

		it( 'returns labels and icons for all registered types', () => {
			const registry = new TypeSpecificComponentRegistry();

			registerComponent( registry, 'string', { label: 'String', icon: 'string-icon' } );
			registerComponent( registry, 'number', { label: 'Number', icon: 'number-icon' } );
			registerComponent( registry, 'boolean', { label: 'Boolean', icon: 'boolean-icon' } );

			expect( registry.getLabelsAndIcons() ).toStrictEqual( [
				{ value: 'string', label: 'String', icon: 'string-icon' },
				{ value: 'number', label: 'Number', icon: 'number-icon' },
				{ value: 'boolean', label: 'Boolean', icon: 'boolean-icon' }
			] );
		} );

	} );

	describe( 'getValueDisplayComponent', () => {
		it( 'returns the correct value display component for a registered type', () => {
			const registry = new TypeSpecificComponentRegistry();
			registerComponent( registry, 'url', { valueDisplayComponent: { name: 'UrlWrong' } } );
			registerComponent( registry, 'string', { valueDisplayComponent: { name: 'StringRight' } } );
			registerComponent( registry, 'number', { valueDisplayComponent: { name: 'NumberWrong' } } );

			const result = registry.getValueDisplayComponent( 'string' );
			expect( result ).toStrictEqual( { name: 'StringRight' } );
		} );

		it( 'throws an error for an unregistered type', () => {
			const registry = new TypeSpecificComponentRegistry();
			expect( () => registry.getValueDisplayComponent( 'unregistered' ) )
				.toThrow( 'Unknown property type: unregistered' );
		} );
	} );

	describe( 'getValueEditingComponent', () => {
		it( 'returns the correct value editing component for a registered type', () => {
			const registry = new TypeSpecificComponentRegistry();
			registerComponent( registry, 'url', { valueEditor: { name: 'UrlWrong' } } );
			registerComponent( registry, 'string', { valueEditor: { name: 'StringRight' } } );
			registerComponent( registry, 'number', { valueEditor: { name: 'NumberWrong' } } );

			const result = registry.getValueEditingComponent( 'string' );
			expect( result ).toStrictEqual( { name: 'StringRight' } );
		} );

		it( 'throws an error for an unregistered type', () => {
			const registry = new TypeSpecificComponentRegistry();
			expect( () => registry.getValueEditingComponent( 'unregistered' ) )
				.toThrow( 'Unknown property type: unregistered' );
		} );
	} );

	describe( 'getAttributesEditor', () => {
		it( 'returns the correct attributes editor component for a registered type', () => {
			const registry = new TypeSpecificComponentRegistry();
			registerComponent( registry, 'url', { attributesEditor: { name: 'UrlWrong' } } );
			registerComponent( registry, 'string', { attributesEditor: { name: 'StringRight' } } );
			registerComponent( registry, 'number', { attributesEditor: { name: 'NumberWrong' } } );

			const result = registry.getAttributesEditor( 'string' );
			expect( result ).toStrictEqual( { name: 'StringRight' } );
		} );

		it( 'throws an error for an unregistered type', () => {
			const registry = new TypeSpecificComponentRegistry();
			expect( () => registry.getAttributesEditor( 'unregistered' ) )
				.toThrow( 'Unknown property type: unregistered' );
		} );
	} );

	describe( 'getLabel', () => {
		it( 'returns the label for a registered type', () => {
			const registry = new TypeSpecificComponentRegistry();
			registerComponent( registry, 'url', { label: 'UrlWrong' } );
			registerComponent( registry, 'string', { label: 'StringRight' } );
			registerComponent( registry, 'number', { label: 'NumberWrong' } );

			const result = registry.getLabel( 'string' );
			expect( result ).toEqual( 'StringRight' );
		} );

		it( 'throws an error for an unregistered type', () => {
			const registry = new TypeSpecificComponentRegistry();
			expect( () => registry.getLabel( 'unregistered' ) )
				.toThrow( 'Unknown property type: unregistered' );
		} );
	} );

} );
