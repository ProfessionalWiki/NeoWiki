import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useSubjectEditor } from '@/composables/useSubjectEditor';
import { newSchema, newSubject } from '@/TestHelpers.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import type { Subject } from '@/domain/Subject.ts';
import { Schema } from '@/domain/Schema.ts';
import { setupMwMock } from '../VueTestHelpers.ts';

const SUBJECT_ID = new SubjectId( 's1aaaaaaaaaaaa1' );
const OTHER_SUBJECT_ID = new SubjectId( 's1bbbbbbbbbbbb1' );

const SCHEMAS: Record<string, Schema> = {
	Company: newSchema( { title: 'Company' } ),
	Person: newSchema( { title: 'Person' } ),
};

const storedSubject = newSubject( { id: SUBJECT_ID, schemaName: 'Company' } );

const getSubjectForEditing = vi.fn();
const getSchema = vi.fn();

function newOpener(): ReturnType<typeof useSubjectEditor> {
	return useSubjectEditor( { getSubjectForEditing }, { getSchema } );
}

interface Deferred<T> {
	promise: Promise<T>;
	resolve: ( value: T ) => void;
	reject: ( error: Error ) => void;
}

function deferred<T>(): Deferred<T> {
	let resolve!: ( value: T ) => void;
	let reject!: ( error: Error ) => void;

	const promise = new Promise<T>( ( resolveValue, rejectValue ) => {
		resolve = resolveValue;
		reject = rejectValue;
	} );

	return { promise, resolve, reject };
}

describe( 'useSubjectEditor', () => {

	beforeEach( () => {
		setupMwMock();
		getSubjectForEditing.mockReset().mockResolvedValue( storedSubject );
		getSchema.mockReset().mockImplementation( ( name: string ) =>
			SCHEMAS[ name ] === undefined ?
				Promise.reject( new Error( `Unknown schema: ${ name }` ) ) :
				Promise.resolve( SCHEMAS[ name ] ) );
	} );

	it( 'starts closed with nothing to edit', () => {
		const { editingSubject, editingSchema, editorOpen } = newOpener();

		expect( editingSubject.value ).toBeNull();
		expect( editingSchema.value ).toBeNull();
		expect( editorOpen.value ).toBe( false );
	} );

	it( 'opens on the Subject the repository answers with for the id it was given', async () => {
		const { editingSubject, editorOpen, openEditor } = newOpener();

		await openEditor( SUBJECT_ID );

		expect( getSubjectForEditing ).toHaveBeenCalledWith( SUBJECT_ID );
		expect( editingSubject.value ).toBe( storedSubject );
		expect( editorOpen.value ).toBe( true );
	} );

	// The fetched Subject names a different Schema from every other fixture here, so an
	// implementation reading the name off anything but this Subject asks for the wrong one.
	it( 'opens on the Schema the freshly fetched Subject names', async () => {
		getSubjectForEditing.mockResolvedValue( newSubject( { id: SUBJECT_ID, schemaName: 'Person' } ) );
		const { editingSchema, openEditor } = newOpener();

		await openEditor( SUBJECT_ID );

		expect( getSchema ).toHaveBeenCalledWith( 'Person' );
		expect( editingSchema.value ).toBe( SCHEMAS.Person );
	} );

	it( 'asks for no Schema when the Subject could not be read', async () => {
		getSubjectForEditing.mockRejectedValue( new Error( 'network down' ) );
		const { openEditor } = newOpener();

		await openEditor( SUBJECT_ID );

		expect( getSchema ).not.toHaveBeenCalled();
	} );

	it( 'stays closed and reports the failure when the Subject could not be read', async () => {
		getSubjectForEditing.mockRejectedValue( new Error( 'network down' ) );
		const { editingSubject, editorOpen, openEditor } = newOpener();

		await expect( openEditor( SUBJECT_ID ) ).resolves.toBeUndefined();

		expect( editorOpen.value ).toBe( false );
		expect( editingSubject.value ).toBeNull();
		expect( mw.notify ).toHaveBeenCalledWith( 'network down', { type: 'error' } );
	} );

	// Both or neither: a Subject held back until its Schema lands cannot be rendered by the
	// Schema of whatever the editor last opened.
	it( 'holds back the Subject too when its Schema could not be read', async () => {
		getSubjectForEditing.mockResolvedValue( newSubject( { id: SUBJECT_ID, schemaName: 'Deleted' } ) );
		const { editingSubject, editingSchema, editorOpen, openEditor } = newOpener();

		await expect( openEditor( SUBJECT_ID ) ).resolves.toBeUndefined();

		expect( editorOpen.value ).toBe( false );
		expect( editingSubject.value ).toBeNull();
		expect( editingSchema.value ).toBeNull();
		expect( mw.notify ).toHaveBeenCalledWith( 'Unknown schema: Deleted', { type: 'error' } );
	} );

	it( 'leaves what it last opened alone when a later open fails', async () => {
		const opener = newOpener();
		await opener.openEditor( SUBJECT_ID );

		getSubjectForEditing.mockRejectedValue( new Error( 'network down' ) );
		await opener.openEditor( SUBJECT_ID );

		expect( opener.editingSubject.value ).toBe( storedSubject );
		expect( opener.editingSchema.value ).toBe( SCHEMAS.Company );
		expect( opener.editorOpen.value ).toBe( true );
	} );

	describe( 'when a second open starts before the first has landed', () => {

		const secondSubject = newSubject( { id: OTHER_SUBJECT_ID, schemaName: 'Person' } );

		/** Starts an open that is still in flight, then lets a second one finish ahead of it. */
		function supersededOpen(): { slow: Deferred<Subject>; first: Promise<void>; opener: ReturnType<typeof useSubjectEditor> } {
			const slow = deferred<Subject>();
			getSubjectForEditing
				.mockImplementationOnce( () => slow.promise )
				.mockImplementationOnce( () => Promise.resolve( secondSubject ) );
			const opener = newOpener();

			const first = opener.openEditor( SUBJECT_ID );

			return { slow, first, opener };
		}

		it( 'opens on the newest, even when the earlier read lands last', async () => {
			const { slow, first, opener } = supersededOpen();
			await opener.openEditor( OTHER_SUBJECT_ID );

			slow.resolve( storedSubject );
			await first;

			expect( opener.editingSubject.value ).toBe( secondSubject );
			expect( opener.editingSchema.value ).toBe( SCHEMAS.Person );
		} );

		// The Schema read is a round trip whose result the guard would only throw away.
		it( 'does not read a Schema for the superseded open', async () => {
			const { slow, first, opener } = supersededOpen();
			await opener.openEditor( OTHER_SUBJECT_ID );
			getSchema.mockClear();

			slow.resolve( storedSubject );
			await first;

			expect( getSchema ).not.toHaveBeenCalled();
		} );

		it( 'does not report a superseded open that fails', async () => {
			const { slow, first, opener } = supersededOpen();
			await opener.openEditor( OTHER_SUBJECT_ID );

			slow.reject( new Error( 'network down' ) );
			await first;

			expect( mw.notify ).not.toHaveBeenCalled();
			expect( opener.editorOpen.value ).toBe( true );
		} );

	} );

	it( 'gives each caller its own editor state', async () => {
		const first = newOpener();
		const second = newOpener();

		await first.openEditor( SUBJECT_ID );

		expect( second.editingSubject.value ).toBeNull();
		expect( second.editorOpen.value ).toBe( false );
	} );

} );
