import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import SchemaEditor, { type SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { newNumberProperty } from '@/domain/propertyTypes/Number.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import { newSchema } from '@/TestHelpers.ts';
import { newRelationProperty, type RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import type { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { createI18nMock, findPropertyNameInput, reportUnparseableNumber, selectedText } from '../../VueTestHelpers.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices.ts';
import PaneDivider from '@/components/common/PaneDivider.vue';
import { nextTick } from 'vue';

function createWrapper( schema: Schema, description = '' ): VueWrapper {
	return mount( SchemaEditor, {
		props: {
			initialSchema: schema,
			description,
		},
		global: {
			mocks: {
				$i18n: createI18nMock(),
			},
			stubs: {
				PropertyList: true,
				PropertyDefinitionEditor: true,
			},
		},
	} );
}

function createWrapperWithPropertyEditor( schema: Schema ): VueWrapper {
	return mount( SchemaEditor, {
		props: {
			initialSchema: schema,
		},
		global: {
			provide: NeoWikiTestServices.getServices(),
			directives: {
				tooltip: {},
			},
			mocks: {
				$i18n: createI18nMock(),
			},
			stubs: {
				PropertyList: true,
			},
		},
	} );
}

describe( 'SchemaEditor', () => {

	beforeEach( () => {
		// The two Constraint messages resolve to real text, so a test asserting on them can tell
		// a rendered message from the bare key.
		const messages: Record<string, string> = {
			'neowiki-property-editor-relation-required': 'Relation type is required.',
			'neowiki-property-editor-target-schema-required': 'Target schema is required.',
		};

		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str ) => ( {
				text: () => messages[ str ] ?? str,
				parse: () => messages[ str ] ?? str,
			} ) ),
		} );
	} );

	it( 'selects the first property by default when properties exist', () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );

		expect( wrapper.classes() ).toContain( 'ext-neowiki-schema-editor--has-selected-property' );
		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'firstProp' );
		expect( wrapper.findComponent( { name: 'PropertyDefinitionEditor' } ).props( 'property' ).name.toString() ).toBe( 'firstProp' );
	} );

	it( 'does not select any property if schema has no properties', () => {
		const schema = new Schema(
			'EmptySchema',
			'Description',
			new PropertyDefinitionList( [] ),
		);

		const wrapper = createWrapper( schema );

		expect( wrapper.classes() ).not.toContain( 'ext-neowiki-schema-editor--has-selected-property' );
		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( undefined );
		expect( wrapper.findComponent( { name: 'PropertyDefinitionEditor' } ).exists() ).toBe( false );
	} );

	it( 'removes property when propertyDeleted event is emitted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyDeleted', schema.getPropertyDefinition( 'firstProp' ).name );

		const updatedSchema = ( wrapper.vm as any ).getSchema();
		expect( updatedSchema.getPropertyDefinitions().has( schema.getPropertyDefinition( 'firstProp' ).name ) ).toBe( false );
		expect( updatedSchema.getPropertyDefinitions().has( schema.getPropertyDefinition( 'secondProp' ).name ) ).toBe( true );
	} );

	it( 'updates selection when selected property is deleted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyDeleted', schema.getPropertyDefinition( 'firstProp' ).name );

		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'secondProp' );
	} );

	it( 'maintains selection when non-selected property is deleted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertySelected', schema.getPropertyDefinition( 'secondProp' ).name );
		await propertyList.vm.$emit( 'propertyDeleted', schema.getPropertyDefinition( 'firstProp' ).name );

		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'secondProp' );
	} );

	it( 'builds the schema with the description supplied by the host', () => {
		const schema = new Schema(
			'TestSchema',
			'The description the editor was handed',
			new PropertyDefinitionList( [] ),
		);

		const wrapper = createWrapper( schema, 'The description the host now holds' );

		const built = ( wrapper.vm as any ).getSchema() as Schema;
		expect( built.getDescription() ).toBe( 'The description the host now holds' );
		expect( built.getName() ).toBe( 'TestSchema' );
	} );

	it( 'keeps the schema its own description when the host presents none', () => {
		const schema = new Schema(
			'TestSchema',
			'The description it arrived with',
			new PropertyDefinitionList( [] ),
		);

		const wrapper = mount( SchemaEditor, {
			props: { initialSchema: schema },
			global: {
				mocks: { $i18n: createI18nMock() },
				stubs: { PropertyList: true, PropertyDefinitionEditor: true },
			},
		} );

		expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getDescription() )
			.toBe( 'The description it arrived with' );
	} );

	it( 'keeps the host description when a property is edited', async () => {
		const schema = new Schema(
			'TestSchema',
			'',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema, 'Held by the host' );
		const editor = wrapper.findComponent( { name: 'PropertyDefinitionEditor' } );
		const updatedProperty = createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName, description: 'Updated' } );
		await editor.vm.$emit( 'update:propertyDefinition', updatedProperty );

		expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getDescription() ).toBe( 'Held by the host' );
	} );

	it( 'emits change when a property is created', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		const newProperty = createPropertyDefinitionFromJson( 'newProp', { type: TextType.typeName } );
		await propertyList.vm.$emit( 'propertyCreated', newProperty );

		expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
	} );

	it( 'emits change when a property is deleted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyDeleted', schema.getPropertyDefinition( 'firstProp' ).name );

		expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
	} );

	it( 'emits change when a property definition is updated', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const editor = wrapper.findComponent( { name: 'PropertyDefinitionEditor' } );

		const updatedProperty = createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName, description: 'Updated' } );
		await editor.vm.$emit( 'update:propertyDefinition', updatedProperty );

		expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
	} );

	it( 'reorders properties when propertyReordered event is emitted', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'thirdProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyReordered', [
			new PropertyName( 'thirdProp' ),
			new PropertyName( 'firstProp' ),
			new PropertyName( 'secondProp' ),
		] );

		const updatedSchema = ( wrapper.vm as any ).getSchema();
		const propertyNames = Object.keys( updatedSchema.getPropertyDefinitions().asRecord() );
		expect( propertyNames ).toEqual( [ 'thirdProp', 'firstProp', 'secondProp' ] );
	} );

	it( 'emits change when properties are reordered', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertyReordered', [
			new PropertyName( 'secondProp' ),
			new PropertyName( 'firstProp' ),
		] );

		expect( wrapper.emitted( 'change' ) ).toHaveLength( 1 );
	} );

	it( 'reinitializes state when initialSchema prop changes', async () => {
		const schema = new Schema(
			'TestSchema',
			'Original',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );

		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'firstProp' );

		const newSchema = new Schema(
			'UpdatedSchema',
			'Updated description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'alphaProperty', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'betaProperty', { type: TextType.typeName } ),
			] ),
		);

		await wrapper.setProps( { initialSchema: newSchema } );

		expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getName() ).toBe( 'UpdatedSchema' );
		expect( wrapper.findComponent( { name: 'PropertyList' } ).props( 'selectedPropertyName' ) ).toBe( 'alphaProperty' );
	} );

	it( 'does not emit change when a property is selected', async () => {
		const schema = new Schema(
			'TestSchema',
			'Description',
			new PropertyDefinitionList( [
				createPropertyDefinitionFromJson( 'firstProp', { type: TextType.typeName } ),
				createPropertyDefinitionFromJson( 'secondProp', { type: TextType.typeName } ),
			] ),
		);

		const wrapper = createWrapper( schema );
		const propertyList = wrapper.findComponent( { name: 'PropertyList' } );

		await propertyList.vm.$emit( 'propertySelected', schema.getPropertyDefinition( 'secondProp' ).name );

		expect( wrapper.emitted( 'change' ) ).toBeUndefined();
	} );

	describe( 'Unparseable initial value', () => {
		function schemaWithScore(): Schema {
			return new Schema(
				'TestSchema',
				'Description',
				new PropertyDefinitionList( [ newNumberProperty( { name: 'Score' } ) ] ),
			);
		}

		/**
		 * Puts the selected property's Initial value field in the state a browser
		 * leaves it in for text like "5foo": the reported value is empty while
		 * validity.badInput is set. jsdom neither keeps such text nor sets the flag.
		 */
		function unparseableInput( wrapper: VueWrapper ): ReturnType<SchemaEditorExposes['unparseableInput']> {
			return ( wrapper.vm as unknown as SchemaEditorExposes ).unparseableInput();
		}

		it( 'reports nothing while the selected property editor reports nothing', () => {
			const wrapper = createWrapperWithPropertyEditor( schemaWithScore() );

			expect( unparseableInput( wrapper ) ).toBeNull();
		} );

		it( 'names the selected property when its editor holds text it cannot turn into a value', async () => {
			const wrapper = createWrapperWithPropertyEditor( schemaWithScore() );

			await reportUnparseableNumber( wrapper.findComponent( NumberInput ).find( 'input' ) );

			expect( unparseableInput( wrapper ) ).toEqual( {
				propertyName: 'Score',
				message: 'neowiki-field-invalid-number',
			} );
		} );

		it( 'keeps holding the text it cannot turn into a value while the property is renamed', async () => {
			const wrapper = createWrapperWithPropertyEditor( schemaWithScore() );
			await reportUnparseableNumber( wrapper.findComponent( NumberInput ).find( 'input' ) );

			await findPropertyNameInput( wrapper ).setValue( 'Points' );
			await flushPromises();

			expect( unparseableInput( wrapper ) ).toEqual( {
				propertyName: 'Points',
				message: 'neowiki-field-invalid-number',
			} );
		} );

		it( 'keeps holding the text it cannot turn into a value when the property is selected again', async () => {
			const wrapper = createWrapperWithPropertyEditor( schemaWithScore() );
			await reportUnparseableNumber( wrapper.findComponent( NumberInput ).find( 'input' ) );

			await wrapper.findComponent( { name: 'PropertyList' } ).vm.$emit( 'propertySelected', new PropertyName( 'Score' ) );
			await flushPromises();

			expect( unparseableInput( wrapper ) ).toEqual( {
				propertyName: 'Score',
				message: 'neowiki-field-invalid-number',
			} );
		} );

		it( 'reports nothing when no property is selected', () => {
			const wrapper = createWrapperWithPropertyEditor( new Schema(
				'EmptySchema',
				'Description',
				new PropertyDefinitionList( [] ),
			) );

			expect( unparseableInput( wrapper ) ).toBeNull();
		} );
	} );
	describe( 'property editor', () => {
		async function selectProperty( wrapper: VueWrapper, name: string ): Promise<void> {
			await wrapper.findComponent( { name: 'PropertyList' } ).vm.$emit( 'propertySelected', new PropertyName( name ) );
			await flushPromises();
		}

		/** Leaves the caret where a keystroke inside the text would. */
		async function typeInto( input: HTMLInputElement, value: string, caret: number ): Promise<void> {
			input.value = value;
			input.setSelectionRange( caret, caret );
			input.dispatchEvent( new Event( 'input' ) );
			await flushPromises();
		}

		it( 'opens on a property just added with its generated name selected, so typing replaces it', async () => {
			const wrapper = createWrapperWithPropertyEditor( newSchema( {
				properties: new PropertyDefinitionList( [ newTextProperty( { name: 'Alpha' } ) ] ),
			} ) );

			await wrapper.findComponent( { name: 'PropertyList' } ).vm.$emit( 'propertyCreated', newTextProperty( { name: 'New Property 1' } ) );
			await selectProperty( wrapper, 'New Property 1' );

			expect( selectedText( findPropertyNameInput( wrapper ).element ) ).toBe( 'New Property 1' );
		} );

		it( 'shows the property that gets selected after another was renamed', async () => {
			const wrapper = createWrapperWithPropertyEditor( newSchema( {
				properties: new PropertyDefinitionList( [
					newTextProperty( { name: 'Alpha' } ),
					newTextProperty( { name: 'Beta' } ),
					newTextProperty( { name: 'Gamma' } ),
				] ),
			} ) );
			await findPropertyNameInput( wrapper ).setValue( 'Alphabet' );

			await selectProperty( wrapper, 'Beta' );

			expect( findPropertyNameInput( wrapper ).element.value ).toBe( 'Beta' );
		} );

		it( 'keeps the caret where the user types inside a property name', async () => {
			const wrapper = createWrapperWithPropertyEditor( newSchema( {
				properties: new PropertyDefinitionList( [ newTextProperty( { name: 'Alpha' } ) ] ),
			} ) );

			await typeInto( findPropertyNameInput( wrapper ).element, 'Alpxha', 4 );

			expect( findPropertyNameInput( wrapper ).element.selectionStart ).toBe( 4 );
		} );

		function schemaWithAlphaAndBeta(): Schema {
			return newSchema( {
				properties: new PropertyDefinitionList( [
					newTextProperty( { name: 'Alpha' } ),
					newTextProperty( { name: 'Beta' } ),
				] ),
			} );
		}

		it( 'keeps both properties when one is given the name of the other', async () => {
			const wrapper = createWrapperWithPropertyEditor( schemaWithAlphaAndBeta() );

			await findPropertyNameInput( wrapper ).setValue( 'Beta' );
			await flushPromises();

			const schema = ( wrapper.vm as unknown as SchemaEditorExposes ).getSchema();
			expect( Object.keys( schema.getPropertyDefinitions().asRecord() ) ).toEqual( [ 'Alpha', 'Beta' ] );
		} );

		it( 'holds the save while a property is given the name of another', async () => {
			const wrapper = createWrapperWithPropertyEditor( schemaWithAlphaAndBeta() );

			await findPropertyNameInput( wrapper ).setValue( 'Beta' );
			await flushPromises();

			expect( ( wrapper.vm as unknown as SchemaEditorExposes ).unparseableInput() ).toEqual( {
				propertyName: 'Alpha',
				message: 'neowiki-property-editor-name-taken',
			} );
		} );

		it( 'leaves the name of an existing property unselected when it gets selected', async () => {
			const wrapper = createWrapperWithPropertyEditor( newSchema( {
				properties: new PropertyDefinitionList( [
					newTextProperty( { name: 'Alpha' } ),
					newTextProperty( { name: 'Beta' } ),
					newTextProperty( { name: 'Gamma' } ),
				] ),
			} ) );

			await selectProperty( wrapper, 'Beta' );

			expect( selectedText( findPropertyNameInput( wrapper ).element ) ).toBe( '' );
		} );
	} );

	describe( 'incompleteProperty', () => {
		function incompleteProperty( wrapper: VueWrapper ): ReturnType<SchemaEditorExposes['incompleteProperty']> {
			return ( wrapper.vm as unknown as SchemaEditorExposes ).incompleteProperty();
		}

		function schemaWith( ...properties: PropertyDefinition[] ): Schema {
			return new Schema( 'Test', '', new PropertyDefinitionList( properties ) );
		}

		// newRelationProperty() fills a placeholder target in, which is not the state
		// switching a property's type to Relation leaves behind.
		function relationPropertyWithoutTarget(): PropertyDefinition {
			const noTarget: Partial<RelationProperty> = { targetSchema: undefined };

			return { ...newRelationProperty( { name: 'Maker', relation: 'Made by' } ), ...noTarget };
		}

		it( 'reports nothing when every relation property has what it needs', () => {
			const wrapper = createWrapper( schemaWith(
				newRelationProperty( { name: 'Maker', relation: 'Made by', targetSchema: 'Company' } ),
			) );

			expect( incompleteProperty( wrapper ) ).toBeNull();
		} );

		it( 'names a relation property left without a target schema', () => {
			const wrapper = createWrapper( schemaWith(
				relationPropertyWithoutTarget(),
			) );

			expect( incompleteProperty( wrapper ) ).toEqual( {
				propertyName: 'Maker',
				message: 'Target schema is required.',
			} );
		} );

		// Only the selected property has an editor mounted, so a probe that asked the editors
		// would miss one the user added and then navigated away from.
		it( 'names an incomplete property that is not the selected one', () => {
			const wrapper = createWrapper( schemaWith(
				newNumberProperty( { name: 'Score' } ),
				relationPropertyWithoutTarget(),
			) );

			expect( incompleteProperty( wrapper )?.propertyName ).toBe( 'Maker' );
		} );

		it( 'leaves properties of other types alone', () => {
			const wrapper = createWrapper( schemaWith( newNumberProperty( { name: 'Score' } ) ) );

			expect( incompleteProperty( wrapper ) ).toBeNull();
		} );
	} );
} );

describe( 'SchemaEditor pane divider', () => {

	const schemaWithProperty = new Schema( 'Test', '', new PropertyDefinitionList( [
		createPropertyDefinitionFromJson( 'Name', { type: 'text' } ),
	] ) );

	const emptySchema = new Schema( 'Test', '', new PropertyDefinitionList( [] ) );

	it( 'sits between the list and the editor', () => {
		const wrapper = createWrapper( schemaWithProperty );

		const children = [ ...wrapper.element.children ].map( ( child ) => child.className );

		expect( wrapper.findComponent( PaneDivider ).exists() ).toBe( true );
		expect( children[ 1 ] ).toContain( 'ext-neowiki-pane-divider' );
	} );

	// The track list and the divider appear together or not at all: a divider left behind
	// would hold a track open for a column that is not rendered.
	it( 'is absent when no property is selected', () => {
		const wrapper = createWrapper( emptySchema );

		expect( wrapper.findComponent( PaneDivider ).exists() ).toBe( false );
	} );

	it( 'points at the property list it sizes', () => {
		const wrapper = createWrapper( schemaWithProperty );

		const listId = wrapper.find( '.ext-neowiki-schema-editor' ).element.children[ 0 ].id;

		expect( listId ).not.toBe( '' );
		expect( wrapper.findComponent( PaneDivider ).props( 'controls' ) ).toBe( listId );
	} );

	it( 'gives the list the width the divider asks for', async () => {
		const wrapper = createWrapper( schemaWithProperty );

		wrapper.findComponent( PaneDivider ).vm.$emit( 'resize', 500 );
		await nextTick();

		expect( wrapper.find( '.ext-neowiki-schema-editor' ).attributes( 'style' ) )
			.toContain( '--ext-neowiki-pane-size: 500px' );
		expect( wrapper.findComponent( PaneDivider ).props( 'size' ) ).toBe( 500 );
	} );

	it( 'remembers the width once the gesture ends', async () => {
		const global = globalThis as unknown as { mw?: Record<string, unknown> };
		const before = global.mw;
		const storage = { get: vi.fn( () => null ), set: vi.fn( () => true ) };
		global.mw = { ...before, storage };

		try {
			const wrapper = createWrapper( schemaWithProperty );
			wrapper.findComponent( PaneDivider ).vm.$emit( 'resize', 500 );
			wrapper.findComponent( PaneDivider ).vm.$emit( 'commit' );
			await nextTick();

			expect( storage.set ).toHaveBeenCalledWith( 'neowiki-schema-editor-pane-size', '500' );
		} finally {
			global.mw = before;
		}
	} );

	it( 'tells the divider the bounds it may move between', () => {
		const divider = createWrapper( schemaWithProperty ).findComponent( PaneDivider );

		expect( divider.props( 'min' ) ).toBe( 192 );
		// jsdom measures nothing, so the bound is the current width and the divider still moves.
		expect( divider.props( 'max' ) ).toBe( 320 );
		expect( divider.props( 'disabled' ) ).toBe( false );
	} );
} );
