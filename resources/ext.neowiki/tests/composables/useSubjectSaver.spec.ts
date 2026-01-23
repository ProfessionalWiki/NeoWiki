import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useSubjectSaver } from '@/composables/useSubjectSaver';
import { useSubjectStore } from '@/stores/SubjectStore';
import { Subject } from '@/domain/Subject';
import { newSubject } from '@/TestHelpers';

vi.mock( '@/stores/SubjectStore', () => ( {
	useSubjectStore: vi.fn(),
} ) );

describe( 'useSubjectSaver', () => {
	const mockUpdateSubject = vi.fn();

	beforeEach( () => {
		vi.clearAllMocks();
		( useSubjectStore as any ).mockReturnValue( {
			updateSubject: mockUpdateSubject,
		} );

		vi.stubGlobal( 'mw', {
			notify: vi.fn(),
		} );
	} );

	const expectSuccessNotification = ( subject: Subject ): void => expect( mw.notify ).toHaveBeenCalledWith(
		`Updated ${ subject.getLabel() }.`,
		{ type: 'success' },
	);

	const expectErrorNotification = ( subject: Subject, error: Error ): void => expect( mw.notify ).toHaveBeenCalledWith(
		error.message,
		{
			title: `Failed to update ${ subject.getLabel() }.`,
			type: 'error',
		},
	);

	it( 'saves subject successfully and notifies user', async () => {
		const { saveSubject } = useSubjectSaver();
		const subject = newSubject();
		const summary = 'Test Summary';

		await saveSubject( subject, summary );

		expect( mockUpdateSubject ).toHaveBeenCalledWith( subject, summary );
		expectSuccessNotification( subject );
	} );

	it( 'uses default summary if none provided', async () => {
		const { saveSubject } = useSubjectSaver();
		const subject = newSubject();

		const success = await saveSubject( subject );

		expect( success ).toBe( true );
		expect( mockUpdateSubject ).toHaveBeenCalledWith( subject, undefined );
		expectSuccessNotification( subject );
	} );

	it( 'handles save error and notifies user', async () => {
		const error = new Error( 'Save failed' );
		mockUpdateSubject.mockRejectedValue( error );

		const { saveSubject } = useSubjectSaver();
		const subject = newSubject();

		const success = await saveSubject( subject, 'summary' );

		expect( success ).toBe( false );
		expectErrorNotification( subject, error );
	} );
} );
