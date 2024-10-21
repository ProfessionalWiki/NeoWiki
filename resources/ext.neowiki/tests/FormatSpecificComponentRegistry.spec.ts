import { describe, expect, it } from 'vitest';
import { FormatSpecificComponentRegistry, FormatSpecificStuff } from '@/FormatSpecificComponentRegistry.ts';

describe( 'FormatSpecificComponentRegistry', () => {

	const registerComponent = (
		registry: FormatSpecificComponentRegistry,
		format: string,
		stuff: Partial<FormatSpecificStuff> = {}
	): void => {
		const mockComponent = {} as any;

		const defaultStuff: FormatSpecificStuff = {
			valueDisplayComponent: mockComponent,
			valueEditor: mockComponent,
			attributesEditor: mockComponent,
			label: 'Default Label',
			icon: 'Default Icon'
		};

		registry.registerFormat( format, { ...defaultStuff, ...stuff } );
	};

	describe( 'getValueFormats', () => {

		it( 'returns an empty array when no formats are registered', () => {
			const registry = new FormatSpecificComponentRegistry();

			expect( registry.getValueFormats() ).toEqual( [] );
		} );

		it( 'returns all registered formats', () => {
			const registry = new FormatSpecificComponentRegistry();

			registerComponent( registry, 'string' );
			registerComponent( registry, 'number' );
			registerComponent( registry, 'boolean' );

			expect( registry.getValueFormats() ).toEqual( [ 'string', 'number', 'boolean' ] );
		} );

	} );

	describe( 'getLabelsAndIcons', () => {

		it( 'returns an empty array when no formats are registered', () => {
			const registry = new FormatSpecificComponentRegistry();

			const labelsAndIcons = registry.getLabelsAndIcons();

			expect( labelsAndIcons ).toEqual( [] );
		} );

		it( 'returns labels and icons for all registered formats', () => {
			const registry = new FormatSpecificComponentRegistry();

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
		it( 'returns the correct value display component for a registered format', () => {
			const registry = new FormatSpecificComponentRegistry();
			registerComponent( registry, 'url', { valueDisplayComponent: { name: 'UrlWrong' } } );
			registerComponent( registry, 'string', { valueDisplayComponent: { name: 'StringRight' } } );
			registerComponent( registry, 'number', { valueDisplayComponent: { name: 'NumberWrong' } } );

			const result = registry.getValueDisplayComponent( 'string' );
			expect( result ).toStrictEqual( { name: 'StringRight' } );
		} );

		it( 'throws an error for an unregistered format', () => {
			const registry = new FormatSpecificComponentRegistry();
			expect( () => registry.getValueDisplayComponent( 'unregistered' ) )
				.toThrow( 'No value display component registered for format: unregistered' );
		} );
	} );

	describe( 'getValueEditingComponent', () => {
		it( 'returns the correct value editing component for a registered format', () => {
			const registry = new FormatSpecificComponentRegistry();
			registerComponent( registry, 'url', { valueEditor: { name: 'UrlWrong' } } );
			registerComponent( registry, 'string', { valueEditor: { name: 'StringRight' } } );
			registerComponent( registry, 'number', { valueEditor: { name: 'NumberWrong' } } );

			const result = registry.getValueEditingComponent( 'string' );
			expect( result ).toStrictEqual( { name: 'StringRight' } );
		} );

		it( 'throws an error for an unregistered format', () => {
			const registry = new FormatSpecificComponentRegistry();
			expect( () => registry.getValueEditingComponent( 'unregistered' ) )
				.toThrow( 'No value editing component registered for format: unregistered' );
		} );
	} );

	describe( 'getAttributesEditor', () => {
		it( 'returns the correct attributes editor component for a registered format', () => {
			const registry = new FormatSpecificComponentRegistry();
			registerComponent( registry, 'url', { attributesEditor: { name: 'UrlWrong' } } );
			registerComponent( registry, 'string', { attributesEditor: { name: 'StringRight' } } );
			registerComponent( registry, 'number', { attributesEditor: { name: 'NumberWrong' } } );

			const result = registry.getAttributesEditor( 'string' );
			expect( result ).toStrictEqual( { name: 'StringRight' } );
		} );

		it( 'throws an error for an unregistered format', () => {
			const registry = new FormatSpecificComponentRegistry();
			expect( () => registry.getAttributesEditor( 'unregistered' ) )
				.toThrow( 'No attributes editor component registered for format: unregistered' );
		} );
	} );

} );
