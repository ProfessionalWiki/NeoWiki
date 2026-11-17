import { mount, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, beforeEach } from 'vitest';
import EditorListItem from '@/components/common/EditorListItem.vue';
import { errorStatus, UNSAVED, type ItemStatus } from '@/components/common/ItemStatus.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const ICON = '<span class="test-icon"></span>';

function mountItem( props: { selected?: boolean; status?: ItemStatus | null } = {}, slots: Record<string, string> = {} ): VueWrapper {
	return mount( EditorListItem, {
		props: { selected: false, ...props },
		slots: { default: 'Birth date', ...slots },
		global: { mocks: { $i18n: createI18nMock() } },
	} );
}

function unsavedMark( wrapper: VueWrapper, within = '' ): boolean {
	return wrapper.find( `${ within } [role="img"][aria-label="neowiki-subject-editor-unsaved"]` ).exists();
}

function errorMessages( wrapper: VueWrapper, within = '' ): string[] {
	return wrapper.findAll( `${ within } svg title` ).map( ( title ) => title.text() );
}

describe( 'EditorListItem', () => {
	beforeEach( () => {
		setupMwMock();
	} );

	it( 'is an option that says it is selected', () => {
		expect( mountItem( { selected: true } ).attributes( 'aria-selected' ) ).toBe( 'true' );
	} );

	it( 'is an option that says it is not selected', () => {
		const wrapper = mountItem( { selected: false } );

		expect( wrapper.attributes( 'role' ) ).toBe( 'option' );
		expect( wrapper.attributes( 'aria-selected' ) ).toBe( 'false' );
	} );

	it( 'shows no mark while there is nothing to say', () => {
		const wrapper = mountItem( { status: null } );

		expect( unsavedMark( wrapper ) ).toBe( false );
		expect( errorMessages( wrapper ) ).toStrictEqual( [] );
	} );

	// The row takes its accessible name from its content, so the mark needs words of its own.
	it( 'says in words that what it stands for has unsaved changes', () => {
		expect( unsavedMark( mountItem( { status: UNSAVED } ) ) ).toBe( true );
	} );

	it( 'marks an error with the message that explains it', () => {
		const wrapper = mountItem( { status: errorStatus( 'A schema with this name already exists.' ) } );

		expect( errorMessages( wrapper ) ).toStrictEqual( [ 'A schema with this name already exists.' ] );
		expect( unsavedMark( wrapper ) ).toBe( false );
	} );

	it( 'puts the mark on its icon when it has one', () => {
		const wrapper = mountItem( { status: errorStatus( 'Incomplete' ) }, { icon: ICON } );

		expect( errorMessages( wrapper, '.ext-neowiki-editor-list-item__icon' ) ).toStrictEqual( [ 'Incomplete' ] );
	} );

	it( 'puts the mark in the gutter before its text when it has no icon', () => {
		const wrapper = mountItem( { status: UNSAVED } );

		expect( wrapper.find( '.ext-neowiki-editor-list-item__icon' ).exists() ).toBe( false );
		expect( unsavedMark( wrapper ) ).toBe( true );
	} );

	it( 'shows its label, second line and actions', () => {
		const wrapper = mountItem( {}, {
			description: '<span class="test-description">Date · Optional</span>',
			actions: '<button class="test-action">Delete</button>',
		} );

		expect( wrapper.text() ).toContain( 'Birth date' );
		expect( wrapper.find( '.test-description' ).exists() ).toBe( true );
		expect( wrapper.find( '.test-action' ).exists() ).toBe( true );
	} );
} );
