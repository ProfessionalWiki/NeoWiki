import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useSubjectValidation } from '@/composables/useSubjectValidation.ts';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';

function violation( code: string ): SubjectViolation {
	return { propertyName: 'Title', code, args: [], valuePartIndex: null };
}

describe( 'useSubjectValidation', () => {
	beforeEach( () => vi.useFakeTimers() );
	afterEach( () => vi.useRealTimers() );

	it( 'coalesces rapid revalidate calls into one validation run after the debounce', async () => {
		const validate = vi.fn().mockResolvedValue( [ violation( 'required' ) ] );
		const { violations, revalidate } = useSubjectValidation( validate, { debounceMs: 300 } );

		revalidate();
		revalidate();
		revalidate();
		await vi.advanceTimersByTimeAsync( 300 );

		expect( validate ).toHaveBeenCalledTimes( 1 );
		expect( violations.value ).toEqual( [ violation( 'required' ) ] );
	} );

	it( 'discards a stale response that resolves after a newer one', async () => {
		let resolveStale!: ( value: SubjectViolation[] ) => void;
		let resolveFresh!: ( value: SubjectViolation[] ) => void;
		const validate = vi.fn()
			.mockImplementationOnce( () => new Promise<SubjectViolation[]>( ( resolve ) => {
				resolveStale = resolve;
			} ) )
			.mockImplementationOnce( () => new Promise<SubjectViolation[]>( ( resolve ) => {
				resolveFresh = resolve;
			} ) );
		const { violations, flush } = useSubjectValidation( validate, { debounceMs: 0 } );

		flush();
		flush();

		// The newer (second) request resolves first.
		resolveFresh( [ violation( 'fresh' ) ] );
		await Promise.resolve();
		expect( violations.value ).toEqual( [ violation( 'fresh' ) ] );

		// The stale (first) request resolves afterwards and must be discarded by the guard.
		resolveStale( [ violation( 'stale' ) ] );
		await Promise.resolve();
		expect( violations.value ).toEqual( [ violation( 'fresh' ) ] );
	} );

	it( 'flush runs validation immediately regardless of debounce', async () => {
		const validate = vi.fn().mockResolvedValue( [] );
		const { flush } = useSubjectValidation( validate, { debounceMs: 5000 } );

		await flush();

		expect( validate ).toHaveBeenCalledTimes( 1 );
	} );
} );
