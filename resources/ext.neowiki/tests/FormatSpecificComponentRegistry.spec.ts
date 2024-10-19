import { describe, expect, it } from 'vitest';
import { FormatSpecificComponentRegistry } from '@/FormatSpecificComponentRegistry.ts';

describe( 'FormatSpecificComponentRegistry', () => {

	describe( 'getValueFormats', () => {

		it( 'returns an empty array when no formats are registered', () => {
			const registry = new FormatSpecificComponentRegistry();

			const formats = registry.getValueFormats();

			expect( formats ).toEqual( [] );
		} );

		it( 'returns all registered formats', () => {
			const registry = new FormatSpecificComponentRegistry();
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			const mockComponent = {} as any;

			registry.registerComponents( 'string', mockComponent, mockComponent );
			registry.registerComponents( 'number', mockComponent, mockComponent );
			registry.registerComponents( 'boolean', mockComponent, mockComponent );

			const formats = registry.getValueFormats();

			expect( formats ).toEqual( [ 'string', 'number', 'boolean' ] );
		} );

	} );

} );
