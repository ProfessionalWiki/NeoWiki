import { describe, it, expect, vi } from 'vitest';
import { ref } from 'vue';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation';

describe( 'useCloseConfirmation', () => {

	it( 'closes immediately when hasChanged is false', () => {
		const close = vi.fn();
		const { requestClose, confirmationOpen } = useCloseConfirmation( ref( false ), close );

		requestClose();

		expect( close ).toHaveBeenCalled();
		expect( confirmationOpen.value ).toBe( false );
	} );

	it( 'opens confirmation when hasChanged is true', () => {
		const close = vi.fn();
		const { requestClose, confirmationOpen } = useCloseConfirmation( ref( true ), close );

		requestClose();

		expect( close ).not.toHaveBeenCalled();
		expect( confirmationOpen.value ).toBe( true );
	} );

	it( 'closes and hides confirmation on confirmClose', () => {
		const close = vi.fn();
		const { requestClose, confirmClose, confirmationOpen } = useCloseConfirmation( ref( true ), close );

		requestClose();
		confirmClose();

		expect( close ).toHaveBeenCalled();
		expect( confirmationOpen.value ).toBe( false );
	} );

	it( 'hides confirmation without closing on cancelClose', () => {
		const close = vi.fn();
		const { requestClose, cancelClose, confirmationOpen } = useCloseConfirmation( ref( true ), close );

		requestClose();
		cancelClose();

		expect( close ).not.toHaveBeenCalled();
		expect( confirmationOpen.value ).toBe( false );
	} );

} );
