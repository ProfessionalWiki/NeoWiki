import { mount, DOMWrapper, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, beforeEach } from 'vitest';
import OpenSubjectList from '@/components/SubjectEditor/OpenSubjectList.vue';
import { SubjectId } from '@/domain/SubjectId.ts';
import { newSubject } from '@/TestHelpers.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import type { Subject } from '@/domain/Subject.ts';

// SubjectId's format (ADR 14) excludes '0', 'O', 'I' and 'l', hence the runs of '1's.
const PERSON_ID = 's1person1111111';
const BIRTH_ID = 's2birth11111111';
const PLACE_ID = 's3city111111111';

const person = newSubject( { id: PERSON_ID, label: 'Rembrandt', schemaName: 'Person' } );

// Nobody named it, so the server derives its Schema name as the display name (ADR 31).
const birth = newSubject( {
	id: BIRTH_ID,
	label: null,
	displayName: 'Birth event',
	displayNameIsGenerated: true,
	schemaName: 'Birth event',
} );

const place = newSubject( { id: PLACE_ID, label: 'Amsterdam', schemaName: 'Place' } );

function mountList( overrides: {
	subjects?: Subject[];
	activeId?: string;
	unsavedIds?: string[];
	names?: ReadonlyMap<string, string>;
	attachTo?: Element;
} = {} ): VueWrapper {
	return mount( OpenSubjectList, {
		props: {
			subjects: overrides.subjects ?? [ person, birth, place ],
			activeId: overrides.activeId ?? PERSON_ID,
			unsavedIds: overrides.unsavedIds ?? [],
			names: overrides.names ?? new Map(),
		},
		attachTo: overrides.attachTo,
		global: {
			mocks: { $i18n: createI18nMock() },
		},
	} );
}

function options( wrapper: VueWrapper ): DOMWrapper<Element>[] {
	return wrapper.findAll( '[role="option"]' );
}

function names( wrapper: VueWrapper ): string[] {
	return options( wrapper ).map( ( option ) => option.get( '.ext-neowiki-open-subject-list__name' ).text() );
}

function selectedIds( wrapper: VueWrapper ): string[] {
	return wrapper.emitted( 'select' )?.map(
		( event ) => ( event[ 0 ] as SubjectId ).text,
	) ?? [];
}

function hasUnsavedDot( option: DOMWrapper<Element> ): boolean {
	return option.find( '.ext-neowiki-unsaved-dot' ).exists();
}

describe( 'OpenSubjectList', () => {
	beforeEach( () => {
		setupMwMock();
	} );

	it( 'lists every subject it is given, in that order, under its name', () => {
		expect( names( mountList( { subjects: [ person, place ] } ) ) )
			.toStrictEqual( [ 'Rembrandt', 'Amsterdam' ] );
	} );

	it( 'marks a subject nobody named as carrying a name the system supplied', () => {
		expect( names( mountList( { subjects: [ birth ] } ) ) ).toStrictEqual( [ '(unnamed Birth event)' ] );
	} );

	it( 'names a subject as its pane names it, where the pane says', () => {
		expect( names( mountList( { names: new Map( [ [ BIRTH_ID, 'Birth of Rembrandt' ] ] ) } ) ) )
			.toStrictEqual( [ 'Rembrandt', 'Birth of Rembrandt', 'Amsterdam' ] );
	} );

	it( 'selects the active subject and nothing else', () => {
		const wrapper = mountList( { activeId: BIRTH_ID } );

		expect( options( wrapper ).map( ( option ) => option.attributes( 'aria-selected' ) ) )
			.toStrictEqual( [ 'false', 'true', 'false' ] );
	} );

	it( 'holds the tab stop on the active subject', () => {
		const wrapper = mountList( { activeId: BIRTH_ID } );

		expect( options( wrapper ).map( ( option ) => option.attributes( 'tabindex' ) ) )
			.toStrictEqual( [ '-1', '0', '-1' ] );
	} );

	it( 'reports the subject that was clicked', async () => {
		const wrapper = mountList();

		await options( wrapper )[ 2 ].trigger( 'click' );

		expect( selectedIds( wrapper ) ).toStrictEqual( [ PLACE_ID ] );
	} );

	// Three rows, so an implementation that printed them in any fixed order other than the one it
	// was given would still be caught.
	it( 'says which subject each row stands for, in the order it was given them', () => {
		const wrapper = mountList( { subjects: [ place, person, birth ] } );

		expect( options( wrapper ).map( ( option ) => option.attributes( 'data-mw-neowiki-subject-id' ) ) )
			.toStrictEqual( [ PLACE_ID, PERSON_ID, BIRTH_ID ] );
	} );

	it( 'marks the subjects with unsaved changes and no others', () => {
		const wrapper = mountList( { unsavedIds: [ BIRTH_ID ] } );

		expect( options( wrapper ).map( hasUnsavedDot ) ).toStrictEqual( [ false, true, false ] );
	} );

	// A row takes its accessible name from its content, and the marker is an empty span: without a
	// role and a label of its own an edited subject announces exactly like a clean one.
	it( 'says in words that a marked subject has unsaved changes', () => {
		const dot = mountList( { unsavedIds: [ BIRTH_ID ] } ).get( '.ext-neowiki-unsaved-dot' );

		expect( dot.attributes( 'role' ) ).toBe( 'img' );
		expect( dot.attributes( 'aria-label' ) ).toBe( 'neowiki-subject-editor-unsaved' );
	} );

	it( 'moves down the list with the down arrow', async () => {
		const wrapper = mountList( { activeId: BIRTH_ID } );

		await options( wrapper )[ 1 ].trigger( 'keydown', { key: 'ArrowDown' } );

		expect( selectedIds( wrapper ) ).toStrictEqual( [ PLACE_ID ] );
	} );

	it( 'moves up the list with the up arrow', async () => {
		const wrapper = mountList( { activeId: BIRTH_ID } );

		await options( wrapper )[ 1 ].trigger( 'keydown', { key: 'ArrowUp' } );

		expect( selectedIds( wrapper ) ).toStrictEqual( [ PERSON_ID ] );
	} );

	it( 'stops at the top of the list rather than wrapping round', async () => {
		const wrapper = mountList( { activeId: PERSON_ID } );

		await options( wrapper )[ 0 ].trigger( 'keydown', { key: 'ArrowUp' } );

		expect( selectedIds( wrapper ) ).toStrictEqual( [] );
	} );

	it( 'stops at the bottom of the list rather than wrapping round', async () => {
		const wrapper = mountList( { activeId: PLACE_ID } );

		await options( wrapper )[ 2 ].trigger( 'keydown', { key: 'ArrowDown' } );

		expect( selectedIds( wrapper ) ).toStrictEqual( [] );
	} );

	// The selection is the tab stop, so a move that left the focus behind would send the next
	// Shift+Tab back to the row the reader arrived from.
	it( 'takes the focus to the row it moves to', async () => {
		const wrapper = mountList( { activeId: PERSON_ID, attachTo: document.body } );

		await options( wrapper )[ 0 ].trigger( 'keydown', { key: 'ArrowDown' } );

		expect( document.activeElement ).toBe( options( wrapper )[ 1 ].element );
		wrapper.unmount();
	} );

	// Otherwise the arrow that moves the selection scrolls the dialog around it as well.
	it( 'keeps the arrow keys from scrolling what is around it', async () => {
		const wrapper = mountList( { activeId: PERSON_ID } );
		const event = new KeyboardEvent( 'keydown', { key: 'ArrowDown', cancelable: true, bubbles: true } );

		options( wrapper )[ 0 ].element.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( true );
	} );

	it( 'leaves keys it does not act on to the dialog around it', async () => {
		const wrapper = mountList();

		await options( wrapper )[ 0 ].trigger( 'keydown', { key: 'Escape' } );

		expect( selectedIds( wrapper ) ).toStrictEqual( [] );
	} );

	it( 'names itself for assistive technology', () => {
		const wrapper = mountList();

		expect( wrapper.get( '[role="listbox"]' ).attributes( 'aria-label' ) )
			.toBe( 'neowiki-subject-editor-open-subjects' );
	} );
} );
