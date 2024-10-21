import { describe, expect, it } from 'vitest';
import { FormatSpecificComponentRegistry } from '@/FormatSpecificComponentRegistry.ts';

describe( 'FormatSpecificComponentRegistry', () => {

	const registerComponent = ( registry: FormatSpecificComponentRegistry, format: string ): void => {
		const mockComponent = {} as any;
		registry.registerComponents( format, mockComponent, mockComponent, 'Label', 'Icon' );
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
			const mockComponent = {} as any;

			registry.registerComponents( 'string', mockComponent, mockComponent, 'String', 'string-icon' );
			registry.registerComponents( 'number', mockComponent, mockComponent, 'Number', 'number-icon' );
			registry.registerComponents( 'boolean', mockComponent, mockComponent, 'Boolean', 'boolean-icon' );

			expect( registry.getLabelsAndIcons() ).toEqual( [
				{ value: 'string', label: 'String', icon: 'string-icon' },
				{ value: 'number', label: 'Number', icon: 'number-icon' },
				{ value: 'boolean', label: 'Boolean', icon: 'boolean-icon' }
			] );
		} );

	} );

} );
