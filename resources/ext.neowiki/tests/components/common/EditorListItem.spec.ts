import { mount, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, beforeEach } from 'vitest';
import EditorListItem from '@/components/common/EditorListItem.vue';
import { errorStatus, UNSAVED, type ItemStatus } from '@/components/common/ItemStatus.ts';
import { setupMwMock, statusInWords } from '../../VueTestHelpers.ts';

const ICON = '<span class="test-icon"></span>';

function mountItem( props: { selected?: boolean; unselectable?: boolean; status?: ItemStatus | null } = {}, slots: Record<string, string> = {} ): VueWrapper {
	return mount( EditorListItem, {
		props: { selected: false, ...props },
		slots: { default: 'Birth date', ...slots },
	} );
}

function marks( wrapper: VueWrapper, within = '' ): string[] {
	return wrapper.findAll( `${ within } .ext-neowiki-status-mark` )
		.map( ( mark ) => mark.classes( 'ext-neowiki-status-mark--error' ) ? 'error' : 'unsaved' );
}

describe( 'EditorListItem', () => {
	beforeEach( () => {
		setupMwMock();
	} );

	it( 'is an option that says it is selected', () => {
		expect( mountItem( { selected: true } ).attributes( 'aria-selected' ) ).toBe( 'true' );
	} );

	it( 'is a plain list item when nothing selects it', () => {
		const wrapper = mountItem( { unselectable: true } );

		expect( wrapper.attributes( 'role' ) ).toBe( 'listitem' );
		expect( wrapper.attributes( 'aria-selected' ) ).toBeUndefined();
	} );

	it( 'is an option that says it is not selected', () => {
		const wrapper = mountItem( { selected: false } );

		expect( wrapper.attributes( 'role' ) ).toBe( 'option' );
		expect( wrapper.attributes( 'aria-selected' ) ).toBe( 'false' );
	} );

	it( 'shows no mark while there is nothing to say', () => {
		const wrapper = mountItem( { status: null } );

		expect( marks( wrapper ) ).toStrictEqual( [] );
		expect( statusInWords( wrapper ) ).toBeNull();
	} );

	it( 'marks unsaved changes', () => {
		expect( marks( mountItem( { status: UNSAVED } ) ) ).toStrictEqual( [ 'unsaved' ] );
	} );

	it( 'marks an error', () => {
		expect( marks( mountItem( { status: errorStatus( 'Target schema is required.' ) } ) ) ).toStrictEqual( [ 'error' ] );
	} );

	// The row takes its accessible name from its content: a screen reader reads the name first, then the status.
	it( 'says its status in words after its label', () => {
		const wrapper = mountItem( { status: UNSAVED } );

		expect( wrapper.text() ).toMatch( /^Birth date\s*neowiki-subject-editor-unsaved$/ );
	} );

	it( 'says an error in the words of its message', () => {
		expect( statusInWords( mountItem( { status: errorStatus( 'A schema with this name already exists.' ) } ) ) )
			.toBe( 'A schema with this name already exists.' );
	} );

	// A mark read as well would say the status twice, the first time ahead of the label.
	it.each( [
		[ 'unsaved', UNSAVED ],
		[ 'error', errorStatus( 'Target schema is required.' ) ],
	] )( 'hides its %s mark from assistive technology', ( _kind, status ) => {
		expect( mountItem( { status } ).get( '.ext-neowiki-status-mark' ).attributes( 'aria-hidden' ) ).toBe( 'true' );
	} );

	it( 'gives the mark a tooltip saying its status', () => {
		const wrapper = mountItem( { status: errorStatus( 'Target schema is required.' ) } );

		expect( wrapper.get( '.ext-neowiki-status-mark' ).attributes( 'title' ) ).toBe( 'Target schema is required.' );
	} );

	it( 'puts the mark on its icon when it has one', () => {
		const wrapper = mountItem( { status: errorStatus( 'Incomplete' ) }, { icon: ICON } );

		expect( marks( wrapper, '.ext-neowiki-editor-list-item__icon' ) ).toStrictEqual( [ 'error' ] );
	} );

	it( 'puts the mark in the gutter before its text when it has no icon', () => {
		const wrapper = mountItem( { status: UNSAVED } );

		expect( wrapper.find( '.ext-neowiki-editor-list-item__icon' ).exists() ).toBe( false );
		expect( marks( wrapper ) ).toStrictEqual( [ 'unsaved' ] );
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

	it( 'ends with what it shows at all times, apart from the actions that show only now and then', () => {
		const wrapper = mountItem( {}, {
			end: '<span class="test-end">›</span>',
			actions: '<button class="test-action">Delete</button>',
		} );

		expect( wrapper.text() ).toMatch( /Birth date.*›/s );
		expect( wrapper.find( '.ext-neowiki-editor-list-item__actions .test-end' ).exists() ).toBe( false );
	} );
} );
