import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { shallowMount, VueWrapper } from '@vue/test-utils';
import { CdxMenuButton } from '@wikimedia/codex';
import SubjectRow from '@/components/SubjectsManager/SubjectRow.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { Statement } from '@/domain/Statement.ts';
import { PropertyName } from '@/domain/PropertyDefinition.ts';
import { newStringValue } from '@/domain/Value.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { newSubject } from '@/TestHelpers.ts';

const SUBJECT_ID = 's1aaaaaaaaaaaa1';
const IRI_BASE = 'https://data.example.org/entity/';
const IRI = IRI_BASE + SUBJECT_ID;

const subject = newSubject( { id: SUBJECT_ID, label: 'ACME Inc', schemaName: 'Company' } );

let writeText: ReturnType<typeof vi.fn>;

function mountRow( props: Record<string, unknown> = {} ): VueWrapper {
	setupMwMock( {
		functions: [ 'config', 'msg', 'message', 'notify', 'util' ],
		config: { wgNeoWikiSubjectIriBase: IRI_BASE },
	} );

	return shallowMount( SubjectRow, {
		props: { subject, expanded: true, ...props },
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: { CdxIcon: true },
		},
	} );
}

describe( 'SubjectRow', () => {

	beforeEach( () => {
		writeText = vi.fn().mockResolvedValue( undefined );
		Object.defineProperty( navigator, 'clipboard', { value: { writeText }, configurable: true } );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	describe( 'the identifiers in the expanded footer', () => {

		it( 'copies the Subject id and says what it copied', async () => {
			const wrapper = mountRow();

			await wrapper.find( '.ext-neowiki-subject-row__id-button' ).trigger( 'click' );

			expect( writeText ).toHaveBeenCalledWith( SUBJECT_ID );
			expect( mw.notify ).toHaveBeenCalledWith(
				'neowiki-managesubjects-id-copied' + SUBJECT_ID,
				{ type: 'success' },
			);
		} );

		it( 'copies the concept URI, not the bare id', async () => {
			const wrapper = mountRow();

			await wrapper.find( '.ext-neowiki-subject-row__iri-button' ).trigger( 'click' );

			expect( writeText ).toHaveBeenCalledWith( IRI );
			expect( mw.notify ).toHaveBeenCalledWith(
				'neowiki-managesubjects-iri-copied' + IRI,
				{ type: 'success' },
			);
		} );

		// A Subject's page is worth naming only where the reader is not already on it, so the surface
		// decides rather than the row reading it off the Subject.
		it( 'links the page a surface names', () => {
			const wrapper = mountRow( { page: new PageIdentifiers( 7, 'ACME Inc' ) } );

			const link = wrapper.find( '.ext-neowiki-subject-row__page-value a' );
			expect( link.text() ).toBe( 'ACME Inc' );
			expect( link.attributes( 'href' ) ).toBe( '/wiki/ACME Inc' );
		} );

		it( 'names no page where the surface names none', () => {
			expect( mountRow().find( '.ext-neowiki-subject-row__page' ).exists() ).toBe( false );
		} );

		// A Subject of another Source is named under that Source's own base, which this wiki does not
		// hold, so the row shows an id and a page rather than an empty entry.
		it( 'shows no concept URI for a Subject of another Source', () => {
			const wrapper = mountRow( { subject: newSubject( { id: 'otherwiki:abc-123' } ) } );

			expect( wrapper.find( '.ext-neowiki-subject-row__iri' ).exists() ).toBe( false );
			expect( wrapper.find( '.ext-neowiki-subject-row__id' ).exists() ).toBe( true );
		} );

		// A clipboard write is refused outright in some browsers and permission setups.
		it.each( [
			[ 'id', 'neowiki-managesubjects-id-copy-error' ],
			[ 'iri', 'neowiki-managesubjects-iri-copy-error' ],
		] )( 'reports a refused %s copy instead of claiming success', async ( which, message ) => {
			vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
			writeText.mockRejectedValue( new Error( 'clipboard denied' ) );

			const wrapper = mountRow();
			await wrapper.find( `.ext-neowiki-subject-row__${ which }-button` ).trigger( 'click' );
			await Promise.resolve();

			expect( mw.notify ).toHaveBeenCalledWith( message, { type: 'error' } );
		} );

	} );

	// The row's only computation: a property the Subject leaves unset is not one of its statements.
	it( 'counts only the statements that hold a value', () => {
		const wrapper = mountRow( { subject: newSubject( {
			id: SUBJECT_ID,
			statements: new StatementList( [
				new Statement( new PropertyName( 'Founded' ), TextType.typeName, newStringValue( '2005' ) ),
				new Statement( new PropertyName( 'Slogan' ), TextType.typeName, undefined ),
			] ),
		} ) } );

		expect( wrapper.find( '.ext-neowiki-subject-row__count' ).text() )
			.toBe( 'neowiki-managesubjects-statement-count1' );
	} );

	describe( 'the Main Subject indicator', () => {

		it( 'is a demote control for a user who may edit', async () => {
			const wrapper = mountRow( { mainSubjectControl: 'demote', canEdit: true } );

			const indicator = wrapper.find( '[aria-label="neowiki-managesubjects-row-demote"]' );
			expect( indicator.exists() ).toBe( true );

			await indicator.trigger( 'click' );
			expect( wrapper.emitted( 'demote' ) ).toHaveLength( 1 );
		} );

		// Nothing to press: the pin only says which Subject the page is about.
		it( 'is a plain marker for a user who may not edit', () => {
			const wrapper = mountRow( { mainSubjectControl: 'demote' } );

			expect( wrapper.find( '[aria-label="neowiki-managesubjects-row-demote"]' ).exists() ).toBe( false );
			expect( wrapper.find( '.ext-neowiki-subject-row__main-indicator' ).exists() ).toBe( true );
		} );

		it( 'is absent where a page\'s Main Subject is not the row\'s business', () => {
			const wrapper = mountRow( { canEdit: true } );

			expect( wrapper.find( '.ext-neowiki-subject-row__main-indicator' ).exists() ).toBe( false );
			expect( wrapper.find( '[aria-label="neowiki-managesubjects-row-promote"]' ).exists() ).toBe( false );
		} );

	} );

	// The page decides what each action does to which Subject, so the row only reports it.
	describe( 'the actions it reports rather than performs', () => {

		const ACTIONS = [ 'copy-link', 'edit', 'promote', 'move', 'delete' ];

		function rowWithEveryAction(): VueWrapper {
			return mountRow( {
				canEdit: true,
				canDelete: true,
				canMove: true,
				mainSubjectControl: 'promote',
			} );
		}

		it.each( ACTIONS )( 'emits %s when its control is used, without toggling the row', async ( action ) => {
			const wrapper = rowWithEveryAction();

			await wrapper.find( `[aria-label="neowiki-managesubjects-row-${ action }"]` ).trigger( 'click' );

			expect( wrapper.emitted( action ) ).toHaveLength( 1 );
			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();
		} );

		it.each( ACTIONS )( 'emits %s when it is chosen from the overflow menu', async ( action ) => {
			const wrapper = rowWithEveryAction();

			wrapper.findComponent( CdxMenuButton ).vm.$emit( 'update:selected', action );
			await wrapper.vm.$nextTick();

			expect( wrapper.emitted( action ) ).toHaveLength( 1 );
			// Cleared, so the same entry can be chosen again straight away.
			expect( wrapper.findComponent( CdxMenuButton ).props( 'selected' ) ).toBeNull();
		} );

		// Both sit inside the header, where a click would otherwise toggle the row.
		it.each( [
			[ 'the drag handle', '[title="neowiki-managesubjects-row-drag-handle"]' ],
			[ 'the Schema badge', '.ext-neowiki-subject-row__schema' ],
		] )( 'leaves the row alone when %s is clicked', async ( _name, selector ) => {
			const wrapper = mountRow( { canEdit: true, showDragHandle: true } );

			await wrapper.find( selector ).trigger( 'click' );

			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();
		} );

		it( 'emits toggle rather than letting the disclosure open itself', async () => {
			const wrapper = mountRow();

			const click = new Event( 'click', { bubbles: true, cancelable: true } );
			wrapper.find( '.ext-neowiki-subject-row__header' ).element.dispatchEvent( click );

			expect( wrapper.emitted( 'toggle' ) ).toHaveLength( 1 );
			// The page owns which rows are open; a summary left to itself would disagree with it.
			expect( click.defaultPrevented ).toBe( true );
		} );

	} );

	// The header toggles the row wherever it is clicked except on its links, and the name is one of
	// them where the surface names a page for the Subject.
	describe( 'the way to the Subject\'s own page', () => {

		const NAME_LINK = 'a.ext-neowiki-subject-row__name';
		const URL = '/wiki/Special:Subject/' + SUBJECT_ID;

		afterEach( () => {
			vi.unstubAllGlobals();
		} );

		it( 'is the Subject\'s name, linked to the page the surface named', () => {
			const wrapper = mountRow( { subjectPageUrl: URL } );

			const link = wrapper.find( NAME_LINK );
			expect( link.attributes( 'href' ) ).toBe( URL );
			expect( link.find( '.ext-neowiki-subject-row__label' ).text() ).toBe( 'ACME Inc' );
			// The cue that it leads somewhere, which CSS shows while the name is pointed at.
			expect( link.find( '.ext-neowiki-subject-row__name-arrow' ).exists() ).toBe( true );
		} );

		// Codex selects a menu item chosen with the keyboard but does not follow its url.
		it( 'goes there when chosen from the overflow menu', async () => {
			vi.stubGlobal( 'location', { href: '' } );
			const wrapper = mountRow( { subjectPageUrl: URL } );

			wrapper.findComponent( CdxMenuButton ).vm.$emit( 'update:selected', 'open' );
			await wrapper.vm.$nextTick();

			expect( location.href ).toBe( URL );
		} );

		it( 'leaves the name plain text on a row the reader is already on', () => {
			const wrapper = mountRow();

			expect( wrapper.find( NAME_LINK ).exists() ).toBe( false );
			expect( wrapper.find( '.ext-neowiki-subject-row__label' ).text() ).toBe( 'ACME Inc' );
			expect( wrapper.findComponent( CdxMenuButton ).props( 'menuItems' ).map( ( item ) => item.value ) )
				.not.toContain( 'open' );
		} );

		// Following the name must not also toggle the row it sits in, and must still be followed: the
		// header one line above it cancels the clicks it handles.
		it( 'keeps a click on the name from reaching the header, and follows it', () => {
			const wrapper = mountRow( { subjectPageUrl: URL } );

			const click = new Event( 'click', { bubbles: true, cancelable: true } );
			wrapper.find( NAME_LINK ).element.dispatchEvent( click );

			expect( wrapper.emitted( 'toggle' ) ).toBeUndefined();
			expect( click.defaultPrevented ).toBe( false );
		} );

	} );

	it( 'shows the drag handle only where the page offers reordering', () => {
		const handle = '[title="neowiki-managesubjects-row-drag-handle"]';

		expect( mountRow( { canEdit: true } ).find( handle ).exists() ).toBe( false );
		expect( mountRow( { canEdit: true, showDragHandle: true } ).find( handle ).exists() ).toBe( true );
	} );

} );
