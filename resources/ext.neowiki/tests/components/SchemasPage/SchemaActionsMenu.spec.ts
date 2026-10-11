import { mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SchemaActionsMenu from '@/components/SchemasPage/SchemaActionsMenu.vue';
import { chooseFromMenu, createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

const MORE_ACTIONS = 'neowiki-schemas-more-actions';

function mountMenu( rights: { canDuplicate: boolean; canDelete: boolean } ): VueWrapper {
	setupMwMock( { functions: [ 'msg', 'message' ] } );

	return mount( SchemaActionsMenu, {
		props: rights,
		global: {
			mocks: { $i18n: createI18nMock() },
			stubs: { CdxIcon: true },
		},
	} );
}

function itemTexts( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( '.cdx-menu-item' ).map( ( item ) => item.text() );
}

describe( 'SchemaActionsMenu', () => {

	it( 'offers Duplicate, then Delete', () => {
		expect( itemTexts( mountMenu( { canDuplicate: true, canDelete: true } ) ) )
			.toEqual( [ 'neowiki-schemas-duplicate', 'neowiki-schemas-delete' ] );
	} );

	it( 'leaves Duplicate out for a user who may not duplicate the Schema', () => {
		expect( itemTexts( mountMenu( { canDuplicate: false, canDelete: true } ) ) ).toEqual( [ 'neowiki-schemas-delete' ] );
	} );

	it( 'leaves Delete out for a user who may not delete the Schema', () => {
		expect( itemTexts( mountMenu( { canDuplicate: true, canDelete: false } ) ) ).toEqual( [ 'neowiki-schemas-duplicate' ] );
	} );

	it( 'shows no menu button to a user who may do neither', () => {
		expect( mountMenu( { canDuplicate: false, canDelete: false } ).find( 'button' ).exists() ).toBe( false );
	} );

	it( 'shows its name as the tooltip of its button', () => {
		expect( mountMenu( { canDuplicate: true, canDelete: true } ).find( 'button' ).attributes( 'title' ) ).toBe( MORE_ACTIONS );
	} );

	it( 'marks Delete as destructive', () => {
		const wrapper = mountMenu( { canDuplicate: true, canDelete: true } );

		expect( wrapper.findAll( '.cdx-menu-item--destructive' ).map( ( item ) => item.text() ) )
			.toEqual( [ 'neowiki-schemas-delete' ] );
	} );

	it.each( [
		[ 'duplicate', 'neowiki-schemas-duplicate' ],
		[ 'delete', 'neowiki-schemas-delete' ],
	] )( 'asks to %s when that is chosen', async ( event, item ) => {
		const wrapper = mountMenu( { canDuplicate: true, canDelete: true } );

		await chooseFromMenu( wrapper, MORE_ACTIONS, item );

		expect( wrapper.emitted( event ) ).toHaveLength( 1 );
	} );

	it( 'marks no action as chosen when it opens again', async () => {
		const wrapper = mountMenu( { canDuplicate: true, canDelete: true } );
		await chooseFromMenu( wrapper, MORE_ACTIONS, 'neowiki-schemas-duplicate' );

		await wrapper.find( `button[aria-label="${ MORE_ACTIONS }"]` ).trigger( 'click' );

		expect( wrapper.findAll( '.cdx-menu-item--selected' ) ).toHaveLength( 0 );
	} );

} );
