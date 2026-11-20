import { enableAutoUnmount, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import DisplayRuleEditor, { type DisplayRuleEditorExposes } from '@/components/LayoutEditor/DisplayRuleEditor.vue';
import NumberDisplayAttributesEditor from '@/components/LayoutEditor/DisplayAttributes/NumberDisplayAttributesEditor.vue';
import { newNumberProperty } from '@/domain/propertyTypes/Number.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import { CdxField } from '@wikimedia/codex';
import { createI18nMock, type FieldProps, setupMwMock } from '../../VueTestHelpers.ts';

// Attached, because where the focus goes is part of what is asserted.
enableAutoUnmount( afterEach );

describe( 'DisplayRuleEditor', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'message' ] } );
	} );

	const VISITORS = newNumberProperty( { name: 'Visitors', precision: 2 } );

	function mountEditor(): VueWrapper {
		return mount( DisplayRuleEditor, {
			props: { property: VISITORS, displayAttributes: { precision: 1 } },
			attachTo: document.body,
			global: {
				provide: NeoWikiTestServices.getServices(),
				mocks: { $i18n: createI18nMock() },
			},
		} );
	}

	function editor( wrapper: VueWrapper ): VueWrapper {
		return wrapper.findComponent( NumberDisplayAttributesEditor );
	}

	it( 'edits the property\'s Display Attributes with its Property Type\'s editor', () => {
		const wrapper = mountEditor();

		expect( editor( wrapper ).props() ).toEqual( { property: VISITORS, displayAttributes: { precision: 1 } } );
	} );

	it( 'passes on the Display Attributes its editor sets', async () => {
		const wrapper = mountEditor();

		await editor( wrapper ).vm.$emit( 'update:displayAttributes', { precision: 4 } );

		expect( wrapper.emitted( 'update:displayAttributes' ) ).toEqual( [ [ { precision: 4 } ] ] );
	} );

	it( 'names the property', () => {
		const wrapper = mountEditor();

		expect( wrapper.find( 'h3' ).text() ).toBe( 'Visitors' );
	} );

	it( 'names its back button, which shows an icon alone, for screen readers', () => {
		const wrapper = mountEditor();

		expect( wrapper.find( '.ext-neowiki-display-rule-editor__back' ).attributes( 'aria-label' ) ).toBe( 'neowiki-layout-editor-back-to-properties' );
	} );

	it( 'goes back to the properties', async () => {
		const wrapper = mountEditor();

		await wrapper.find( '.ext-neowiki-display-rule-editor__back' ).trigger( 'click' );

		expect( wrapper.emitted( 'back' ) ).toEqual( [ [] ] );
	} );

	it( 'starts another property\'s fields afresh, without what was typed for the one before', async () => {
		const wrapper = mountEditor();
		await editor( wrapper ).find( 'input' ).setValue( '-1' );

		await wrapper.setProps( { property: newNumberProperty( { name: 'Staff' } ), displayAttributes: {} } );

		expect( ( editor( wrapper ).find( 'input' ).element as HTMLInputElement ).value ).toBe( '' );
		expect( ( wrapper.findComponent( CdxField ).props() as FieldProps ).messages ).toEqual( {} );
	} );

	it( 'puts the focus in its first field', () => {
		const wrapper = mountEditor();

		( wrapper.vm as unknown as DisplayRuleEditorExposes ).focusFirstField();

		expect( document.activeElement ).toBe( editor( wrapper ).find( 'input' ).element );
	} );

} );
