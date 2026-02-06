import { describe, it, expect } from 'vitest';
import { useChangeDetection } from '@/composables/useChangeDetection';

describe( 'useChangeDetection', () => {

	it( 'starts as false', () => {
		const { hasChanged } = useChangeDetection();

		expect( hasChanged.value ).toBe( false );
	} );

	it( 'becomes true after markChanged', () => {
		const { hasChanged, markChanged } = useChangeDetection();

		markChanged();

		expect( hasChanged.value ).toBe( true );
	} );

	it( 'stays true after multiple markChanged calls', () => {
		const { hasChanged, markChanged } = useChangeDetection();

		markChanged();
		markChanged();

		expect( hasChanged.value ).toBe( true );
	} );

	it( 'becomes false after resetChanged', () => {
		const { hasChanged, markChanged, resetChanged } = useChangeDetection();

		markChanged();
		resetChanged();

		expect( hasChanged.value ).toBe( false );
	} );

	it( 'can be re-marked after resetChanged', () => {
		const { hasChanged, markChanged, resetChanged } = useChangeDetection();

		markChanged();
		resetChanged();
		markChanged();

		expect( hasChanged.value ).toBe( true );
	} );

} );
