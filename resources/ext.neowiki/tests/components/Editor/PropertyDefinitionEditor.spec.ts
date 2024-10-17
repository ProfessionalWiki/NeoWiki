import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PropertyDefinitionEditor from '@/components/Editor/PropertyDefinitionEditor.vue';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { CdxDialog } from '@wikimedia/codex';
import NeoTextField from '@/components/NeoTextField.vue';
import { newTextProperty } from '@neo/domain/valueFormats/Text';
import { Service } from '@/NeoWikiServices';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { newNumberProperty } from '@neo/domain/valueFormats/Number.ts';

type PropertyDefinitionEditorProps = {
	property: PropertyDefinition;
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

	const createWrapper = ( props: Partial<PropertyDefinitionEditorProps> = {} ): VueWrapper => mount( PropertyDefinitionEditor, {
		props: {
			property: newTextProperty(),
			editMode: false,
			...props
		},
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
		const wrapper = createWrapper( {} );
		await wrapper.vm.openDialog();
		expect( wrapper.findComponent( NeoTextField ).exists() ).toBe( true );
	} );

	it( 'updates local property when prop changes', async () => {
		const wrapper = createWrapper( { property: newNumberProperty() } );
		const newProperty = newTextProperty();
		await wrapper.setProps( { property: newProperty } );
		expect( wrapper.vm.localProperty ).toEqual( newProperty );
	} );

	it( 'closes dialog and emits cancel event when cancel is called', async () => {
		const wrapper = createWrapper( {} );
		await wrapper.vm.openDialog();
		expect( wrapper.vm.isOpen ).toBe( true );
		await wrapper.vm.cancel();
		expect( wrapper.vm.isOpen ).toBe( false );
		expect( wrapper.emitted( 'cancel' ) ).toBeTruthy();
	} );

	it( 'renders correct title based on editMode', async () => {
		const editWrapper = createWrapper( { editMode: true } );
		const addWrapper = createWrapper( { editMode: false } );

		expect( editWrapper.findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-property-editor-dialog-title-edit' );
		expect( addWrapper.findComponent( CdxDialog ).props( 'title' ) ).toBe( 'neowiki-property-editor-dialog-title-create' );
	} );
} );
