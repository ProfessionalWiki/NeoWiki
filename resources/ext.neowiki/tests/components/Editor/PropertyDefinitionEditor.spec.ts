import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PropertyDefinitionEditor from '@/components/Editor/PropertyDefinitionEditor.vue';
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition';
import { CdxDialog } from '@wikimedia/codex';
import NeoTextField from '@/components/NeoTextField.vue';
import { newTextProperty } from '@neo/domain/valueFormats/Text';
import { ComponentPublicInstance, DefineComponent } from 'vue';
import { Service } from '@/NeoWikiServices';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { newStringValue } from '@neo/domain/Value';
import { newNumberProperty } from '@neo/domain/valueFormats/Number.ts';

type PropertyDefinitionEditorProps = {
	property: PropertyDefinition;
	editMode: boolean;
};

type PropertyDefinitionEditorVmMethods = {
	isOpen: boolean;
	localProperty: PropertyDefinition;
	openDialog: () => void;
	cancel: () => void;
	save: () => void;
};

type PropertyDefinitionEditorComponent = DefineComponent<
	PropertyDefinitionEditorProps,
	{},
	PropertyDefinitionEditorVmMethods
>;

type TestWrapper = VueWrapper<ComponentPublicInstance<PropertyDefinitionEditorProps, PropertyDefinitionEditorVmMethods>>;

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

	const createWrapper = ( propsData: PropertyDefinitionEditorProps ): TestWrapper => mount<PropertyDefinitionEditorComponent>( PropertyDefinitionEditor as PropertyDefinitionEditorComponent, {
		props: propsData,
		global: {
			mocks: {
				$i18n
			},
			provide: {
				[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getFormatSpecificComponentRegistry()
			}
		}
	} );

	it( 'renders correctly when a property is provided', async () => {
		const wrapper = createWrapper( { property: newTextProperty(), editMode: true } );
		await wrapper.vm.openDialog();
		expect( wrapper.findComponent( NeoTextField ).exists() ).toBe( true );
	} );

	it( 'updates local property when prop changes', async () => {
		const wrapper = createWrapper( { property: newNumberProperty(), editMode: false } );
		const newProperty = newTextProperty();
		await wrapper.setProps( { property: newProperty } );
		expect( wrapper.vm.localProperty ).toEqual( newProperty );
	} );

	it( 'closes dialog and emits cancel event when cancel is called', async () => {
		const wrapper = createWrapper( { property: newTextProperty(), editMode: false } );
		await wrapper.vm.openDialog();
		expect( wrapper.vm.isOpen ).toBe( true );
		await wrapper.vm.cancel();
		expect( wrapper.vm.isOpen ).toBe( false );
		expect( wrapper.emitted( 'cancel' ) ).toBeTruthy();
	} );

	it( 'renders correct title based on editMode', async () => {
		const property: PropertyDefinition = {
			name: new PropertyName( 'testProperty' ),
			format: 'text',
			required: false,
			default: newStringValue( '' ),
			description: ''
		};
		const editWrapper = createWrapper( { property, editMode: true } );
		const addWrapper = createWrapper( { property, editMode: false } );

		expect( editWrapper.findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-property-editor-dialog-title-edit' );
		expect( addWrapper.findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-property-editor-dialog-title-create' );
	} );
} );
