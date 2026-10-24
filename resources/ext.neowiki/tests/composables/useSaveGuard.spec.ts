import { flushPromises } from '@vue/test-utils';
import { describe, expect, it, type Mock, vi } from 'vitest';
import { type SaveHandler, useSaveGuard } from '@/composables/useSaveGuard';

describe( 'useSaveGuard', () => {

	function deferredSave(): { save: Mock<SaveHandler>; resolve: () => void; reject: ( error: Error ) => void } {
		let resolve!: () => void;
		let reject!: ( error: Error ) => void;
		const pending = new Promise<void>( ( resolvePending, rejectPending ) => {
			resolve = resolvePending;
			reject = rejectPending;
		} );
		return { save: vi.fn<SaveHandler>( () => pending ), resolve, reject };
	}

	it( 'ignores a save while another is in flight', async () => {
		const { save: innerSave, resolve } = deferredSave();
		const { save } = useSaveGuard( innerSave );

		save( 'first' );
		save( 'second' );
		resolve();
		await flushPromises();

		expect( innerSave.mock.calls ).toEqual( [ [ 'first' ] ] );
	} );

	it( 'reports saving while a save is in flight', () => {
		const { save: innerSave } = deferredSave();
		const { saving, save } = useSaveGuard( innerSave );

		save( '' );

		expect( saving.value ).toBe( true );
	} );

	it( 'stops reporting saving once the save succeeds', async () => {
		const { save: innerSave, resolve } = deferredSave();
		const { saving, save } = useSaveGuard( innerSave );
		const saved = save( '' );

		resolve();
		await saved;

		expect( saving.value ).toBe( false );
	} );

	it( 'stops reporting saving once the save fails', async () => {
		const { save: innerSave, reject } = deferredSave();
		const { saving, save } = useSaveGuard( innerSave );
		const saved = save( '' );

		reject( new Error( 'Edit conflict' ) );
		await saved.catch( () => undefined );

		expect( saving.value ).toBe( false );
	} );

} );
