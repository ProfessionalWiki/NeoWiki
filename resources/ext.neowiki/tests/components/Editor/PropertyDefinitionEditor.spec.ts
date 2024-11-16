import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PropertyDefinitionEditor from '@/components/Editor/PropertyDefinitionEditor.vue';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { CdxDialog, CdxSelect } from '@wikimedia/codex';
import NeoTextField from '@/components/NeoTextField.vue';
import { newTextProperty } from '@neo/domain/valueFormats/Text';
import { Service } from '@/NeoWikiServices';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { newNumberProperty } from '@neo/domain/valueFormats/Number.ts';

type PropertyDefinitionEditorProps = {
	property: PropertyDefinition;
	isOpen: boolean;
	editMode: boolean;
};

const $i18n = vi.fn().mockImplementation( ( key ) => ( {
	text: () => key
} ) );

describe( 'PropertyDefinitionEditor', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	const createWrapper = ( props: Partial<PropertyDefinitionEditorProps> = {} ): any => mount( PropertyDefinitionEditor, {
		props: {
			property: newTextProperty(),
			isOpen: true,
			editMode: false,
			...props
		},
		global: {
			mocks: {
				$i18n
			},
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getFormatSpecificComponentRegistry(),
				[ Service.ValueFormatRegistry ]: NeoWikiExtension.getInstance().getValueFormatRegistry()
			}
		}
	} );

	it( 'renders correctly when a property is provided', () => {
		const wrapper = createWrapper();
		expect( wrapper.findComponent( NeoTextField ).exists() ).toBe( true );
	} );

	it( 'updates local property when prop changes', async () => {
		const wrapper = createWrapper( { property: newNumberProperty() } );
		const newProperty = newTextProperty();
		await wrapper.setProps( { property: newProperty } );
		expect( wrapper.vm.localProperty ).toEqual( newProperty );
	} );

	it( 'emits cancel event when dialog is closed', async () => {
		const wrapper = createWrapper();
		await wrapper.findComponent( CdxDialog ).vm.$emit( 'update:open' );
		expect( wrapper.emitted( 'cancel' ) ).toBeTruthy();
	} );

	it( 'renders correct title based on editMode', () => {
		const editWrapper = createWrapper( { editMode: true } );
		const addWrapper = createWrapper( { editMode: false } );

		expect( editWrapper.findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-property-editor-dialog-title-edit' );
		expect( addWrapper.findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-property-editor-dialog-title-create' );
	} );

	it( 'emits save event with updated property when save is called', async () => {
		const wrapper = createWrapper();
		await wrapper.vm.save();
		expect( wrapper.emitted( 'save' ) ).toBeTruthy();
		expect( wrapper.emitted( 'save' )?.[ 0 ][ 0 ] ).toEqual( wrapper.vm.localProperty );
	} );

	it( 'renders the initial value component based on the selected format', async () => {
		const wrapper = createWrapper();

		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'number' );

		expect( wrapper.findComponent( { name: 'TextInput' } ).exists() ).toBe( false );
		expect( wrapper.findComponent( { name: 'NumberInput' } ).exists() ).toBe( true );

		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'text' );

		expect( wrapper.findComponent( { name: 'TextInput' } ).exists() ).toBe( true );
		expect( wrapper.findComponent( { name: 'NumberInput' } ).exists() ).toBe( false );
	} );
} );
