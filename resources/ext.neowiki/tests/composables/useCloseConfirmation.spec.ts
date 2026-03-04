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

	describe( 'with alternate confirmation', () => {

		it( 'opens alternate confirmation when condition is met', () => {
			const close = vi.fn();
			const { requestClose, alternateConfirmationOpen, confirmationOpen } =
				useCloseConfirmation( ref( true ), close, ref( true ) );

			requestClose();

			expect( alternateConfirmationOpen.value ).toBe( true );
			expect( confirmationOpen.value ).toBe( false );
			expect( close ).not.toHaveBeenCalled();
		} );

		it( 'opens standard confirmation when alternate condition is false', () => {
			const close = vi.fn();
			const { requestClose, alternateConfirmationOpen, confirmationOpen } =
				useCloseConfirmation( ref( true ), close, ref( false ) );

			requestClose();

			expect( confirmationOpen.value ).toBe( true );
			expect( alternateConfirmationOpen.value ).toBe( false );
		} );

		it( 'closes on confirmAlternateClose', () => {
			const close = vi.fn();
			const { requestClose, confirmAlternateClose, alternateConfirmationOpen } =
				useCloseConfirmation( ref( true ), close, ref( true ) );

			requestClose();
			confirmAlternateClose();

			expect( close ).toHaveBeenCalled();
			expect( alternateConfirmationOpen.value ).toBe( false );
		} );

		it( 'hides alternate confirmation without closing on cancelAlternateClose', () => {
			const close = vi.fn();
			const { requestClose, cancelAlternateClose, alternateConfirmationOpen } =
				useCloseConfirmation( ref( true ), close, ref( true ) );

			requestClose();
			cancelAlternateClose();

			expect( close ).not.toHaveBeenCalled();
			expect( alternateConfirmationOpen.value ).toBe( false );
		} );

		it( 'closes immediately when hasChanged is false regardless of alternate condition', () => {
			const close = vi.fn();
			const { requestClose, alternateConfirmationOpen, confirmationOpen } =
				useCloseConfirmation( ref( false ), close, ref( true ) );

			requestClose();

			expect( close ).toHaveBeenCalled();
			expect( alternateConfirmationOpen.value ).toBe( false );
			expect( confirmationOpen.value ).toBe( false );
		} );

	} );

} );
