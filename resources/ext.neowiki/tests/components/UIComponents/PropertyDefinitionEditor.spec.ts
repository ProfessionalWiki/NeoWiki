import { mount, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import PropertyDefinitionEditor from '@/components/UIComponents/PropertyDefinitionEditor.vue';
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition';
import { CdxDialog } from '@wikimedia/codex';
import NeoTextField from '@/components/NeoTextField.vue';
import { newTextProperty } from '@neo/domain/valueFormats/Text';
import { ComponentPublicInstance, DefineComponent } from 'vue';
import { Service } from '@/NeoWikiServices';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { newStringValue, newNumberValue } from '@neo/domain/Value';

type PropertyDefinitionEditorProps = {
	property: PropertyDefinition | null;
	editMode: boolean;
};

type PropertyDefinitionEditorVmMethods = {
	isOpen: boolean;
	localProperty: PropertyDefinition | null;
	updateForm: ( field: string, value: unknown ) => void;
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

	it( 'renders correctly when no property is provided', async () => {
		const wrapper = createWrapper( { property: null, editMode: false } );
		expect( wrapper.findComponent( NeoTextField ).exists() ).toBe( false );
	} );

	it( 'renders correctly when a property is provided', async () => {
		const wrapper = createWrapper( { property: newTextProperty(), editMode: true } );
		await wrapper.vm.openDialog();
		expect( wrapper.findComponent( NeoTextField ).exists() ).toBe( true );
	} );

	it( 'updates local property when prop changes', async () => {
		const wrapper = createWrapper( { property: null, editMode: false } );
		const newProperty = newTextProperty();
		await wrapper.setProps( { property: newProperty } );
		expect( wrapper.vm.localProperty ).toEqual( newProperty );
	} );

	it( 'emits save event with updated default property', async () => {
		const property = newTextProperty();
		const wrapper = createWrapper( { property, editMode: true } );
		await wrapper.vm.updateForm( 'default', newStringValue( 'new default value' ) );
		await wrapper.vm.save();
		expect( wrapper.emitted( 'save' )?.[ 0 ][ 0 ] ).toEqual( {
			...property,
			default: newStringValue( 'new default value' )
		} );
	} );

	it( 'handles form updates correctly', async () => {
		const property: PropertyDefinition = newTextProperty();
		const wrapper = createWrapper( { property, editMode: true } );
		await wrapper.vm.updateForm( 'name', 'newName' );
		await wrapper.vm.updateForm( 'format', 'number' );
		await wrapper.vm.updateForm( 'required', true );
		await wrapper.vm.updateForm( 'default', newNumberValue( 42 ) );
		await wrapper.vm.updateForm( 'description', 'New description' );

		expect( wrapper.vm.localProperty ).toEqual( {
			name: new PropertyName( 'newName' ),
			format: 'number',
			required: true,
			default: newNumberValue( 42 ),
			description: 'New description',
			multiple: false,
			uniqueItems: true
		} );
	} );

	it( 'closes dialog and emits cancel event when cancel is called', async () => {
		const wrapper = createWrapper( { property: null, editMode: false } );
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

		expect( editWrapper.findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-infobox-editor-dialog-title-edit' );
		expect( addWrapper.findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-infobox-editor-add-property' );
	} );
} );
