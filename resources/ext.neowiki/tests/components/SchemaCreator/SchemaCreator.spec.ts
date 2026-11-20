import { DOMWrapper, mount, VueWrapper, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { accessibleName, createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Service } from '@/NeoWikiServices.ts';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { newTextProperty } from '@/domain/propertyTypes/Text.ts';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';
import { newSchema } from '@/TestHelpers.ts';

const EXISTING_SCHEMA_NAME = 'Person';
const NEW_SCHEMA_NAME = 'Company';
const DEBOUNCE_DELAY = 300;

// The reason the stubbed editor reports for holding the save, the Schema it holds, and whether it
// was asked to reveal its errors or to show the Schema's row. Reset per test by the beforeEach below.
let editorSaveBlocker: SaveBlocker | null = null;
let editorSchema: Schema;
let editorErrorsRevealed = false;
let editorShowedSchema = false;

// Shows the creator's fields for the Schema's own row, as the real editor does while that row is selected.
const SchemaEditorStub = defineComponent( {
	name: 'SchemaEditor',
	template: '<div class="schema-editor-stub"><div class="name-slot"><slot name="name" /></div><slot name="schema" /></div>',
	props: {
		initialSchema: { type: Object, required: true },
		subjectsAreDependent: { type: Boolean, default: undefined },
		schemaError: { type: String, default: null },
	},
	emits: [ 'change' ],
	methods: {
		showSchema(): void {
			editorShowedSchema = true;
		},
		getSchema(): Schema {
			return editorSchema;
		},
		saveBlocker(): SaveBlocker | null {
			return editorSaveBlocker;
		},
		revealErrors(): void {
			editorErrorsRevealed = true;
		},
	},
} );

describe( 'SchemaCreator', () => {
	let pinia: ReturnType<typeof createPinia>;
	let schemaStore: ReturnType<typeof useSchemaStore>;

	interface CreatorProps {
		name?: string;
		duplicateOf?: Schema;
		subjectsAreDependent?: boolean;
	}

	function mountComponent( props: CreatorProps = {} ): VueWrapper {
		return mount( SchemaCreator, {
			props,
			global: {
				plugins: [ pinia ],
				stubs: {
					SchemaEditor: SchemaEditorStub,
					CdxField: {
						name: 'CdxField',
						template: '<div class="cdx-field-stub"><slot /><slot name="label" /></div>',
						props: [ 'status', 'messages' ],
					},
					CdxTextInput: {
						template: '<input class="cdx-text-input-stub" :value="modelValue" @input="$emit( \'update:modelValue\', $event.target.value ); $emit( \'input\' )" />',
						props: [ 'modelValue', 'placeholder' ],
						emits: [ 'update:modelValue', 'input' ],
					},
					CdxTextArea: {
						template: '<textarea class="cdx-text-area-stub" :value="modelValue" @input="$emit( \'update:modelValue\', $event.target.value )"></textarea>',
						props: [ 'modelValue', 'placeholder' ],
						emits: [ 'update:modelValue' ],
					},
				},
				provide: {
					[ Service.ComponentRegistry ]: NeoWikiExtension.getInstance().getTypeSpecificComponentRegistry(),
					[ Service.PropertyTypeRegistry ]: NeoWikiExtension.getInstance().getPropertyTypeRegistry(),
				},
				mocks: {
					$i18n: createI18nMock(),
				},
			},
		} );
	}

	beforeEach( () => {
		editorSaveBlocker = null;
		editorSchema = newSchema( { title: '', description: '', properties: new PropertyDefinitionList( [] ) } );
		editorErrorsRevealed = false;
		editorShowedSchema = false;
		vi.useFakeTimers();

		setupMwMock( {
			functions: [ 'msg', 'notify' ],
		} );

		pinia = createPinia();
		setActivePinia( pinia );

		schemaStore = useSchemaStore();
		schemaStore.schemaNameExists = vi.fn().mockResolvedValue( false );
	} );

	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'does not show error on initially empty field', async () => {
		const wrapper = mountComponent();
		await flushPromises();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'default' );
	} );

	it( 'shows required error in real time when name is cleared', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( 'A' );
		await nameInput.setValue( '' );

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'error' );
	} );

	it( 'clears name error when user types', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( 'A' );
		await nameInput.setValue( '' );

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'error' );

		await nameInput.setValue( 'B' );
		await nameInput.trigger( 'input' );
		await flushPromises();

		expect( field.props( 'status' ) ).toBe( 'default' );
	} );

	it( 'shows name-taken error after debounce', async () => {
		schemaStore.schemaNameExists = vi.fn().mockResolvedValue( true );

		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'default' );

		vi.advanceTimersByTime( DEBOUNCE_DELAY );
		await flushPromises();

		expect( field.props( 'status' ) ).toBe( 'error' );
	} );

	it( 'hands the editor a taken name\'s error for the Schema\'s row, which another row may hide the field from', async () => {
		schemaStore.schemaNameExists = vi.fn().mockResolvedValue( true );
		const wrapper = mountComponent();

		await wrapper.find( '.cdx-text-input-stub' ).setValue( EXISTING_SCHEMA_NAME );
		vi.advanceTimersByTime( DEBOUNCE_DELAY );
		await flushPromises();

		expect( wrapper.findComponent( SchemaEditorStub ).props( 'schemaError' ) ).toBe( 'neowiki-schema-creator-name-taken' );
	} );

	it( 'does not check for duplicates before debounce delay', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY - 1 );
		await flushPromises();

		expect( schemaStore.schemaNameExists ).not.toHaveBeenCalled();
	} );

	it( 'cancels pending duplicate check when user types again', async () => {
		schemaStore.schemaNameExists = vi.fn().mockImplementation(
			( name: string ) => Promise.resolve( name === EXISTING_SCHEMA_NAME ),
		);

		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY - 1 );
		await nameInput.setValue( NEW_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY );
		await flushPromises();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'default' );
		expect( schemaStore.schemaNameExists ).toHaveBeenCalledWith( NEW_SCHEMA_NAME );
		expect( schemaStore.schemaNameExists ).not.toHaveBeenCalledWith( EXISTING_SCHEMA_NAME );
	} );

	it( 'discards stale duplicate check result when user types during request', async () => {
		let resolveExists: ( value: boolean ) => void;
		schemaStore.schemaNameExists = vi.fn().mockImplementation(
			() => new Promise<boolean>( ( resolve ) => {
				resolveExists = resolve;
			} ),
		);

		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( EXISTING_SCHEMA_NAME );

		vi.advanceTimersByTime( DEBOUNCE_DELAY );

		await nameInput.setValue( NEW_SCHEMA_NAME );

		resolveExists!( true );
		await flushPromises();

		const field = wrapper.findComponent( { name: 'CdxField' } );
		expect( field.props( 'status' ) ).toBe( 'default' );
	} );

	it( 'shows the Schema\'s row a task after it mounts, which focuses the name', () => {
		mountComponent();

		vi.runAllTimers();

		expect( editorShowedSchema ).toBe( true );
	} );

	describe( 'validate', () => {
		it( 'shows the Schema\'s row when the name is missing', async () => {
			const wrapper = mountComponent();

			await ( wrapper.vm as any ).validate();

			expect( editorShowedSchema ).toBe( true );
		} );

		it( 'shows the Schema\'s row when the name is taken', async () => {
			schemaStore.schemaNameExists = vi.fn().mockResolvedValue( true );
			const wrapper = mountComponent();
			await wrapper.find( '.cdx-text-input-stub' ).setValue( EXISTING_SCHEMA_NAME );

			await ( wrapper.vm as any ).validate();

			expect( editorShowedSchema ).toBe( true );
		} );

		it( 'leaves the row selected where it is when the name is free', async () => {
			const wrapper = mountComponent();
			await wrapper.find( '.cdx-text-input-stub' ).setValue( NEW_SCHEMA_NAME );

			await ( wrapper.vm as any ).validate();

			expect( editorShowedSchema ).toBe( false );
		} );

		it( 'returns false and shows error when name is empty', async () => {
			const wrapper = mountComponent();

			const valid = await ( wrapper.vm as any ).validate();

			expect( valid ).toBe( false );
			const field = wrapper.findComponent( { name: 'CdxField' } );
			expect( field.props( 'status' ) ).toBe( 'error' );
		} );

		it( 'returns false when name already exists', async () => {
			schemaStore.schemaNameExists = vi.fn().mockResolvedValue( true );

			const wrapper = mountComponent();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( EXISTING_SCHEMA_NAME );
			await flushPromises();

			const valid = await ( wrapper.vm as any ).validate();

			expect( valid ).toBe( false );
			const field = wrapper.findComponent( { name: 'CdxField' } );
			expect( field.props( 'status' ) ).toBe( 'error' );
		} );

		it( 'returns true when name is available', async () => {
			const wrapper = mountComponent();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			const valid = await ( wrapper.vm as any ).validate();

			expect( valid ).toBe( true );
		} );
	} );

	describe( 'getSchema', () => {
		it( 'returns null when name is empty', () => {
			const wrapper = mountComponent();

			const schema = ( wrapper.vm as any ).getSchema();

			expect( schema ).toBeNull();
		} );

		it( 'returns the schema with the name and description that were entered', async () => {
			const wrapper = mountComponent();

			await wrapper.find( '.cdx-text-input-stub' ).setValue( NEW_SCHEMA_NAME );
			await wrapper.find( '.cdx-text-area-stub' ).setValue( 'What this schema is for' );
			await flushPromises();

			const schema = ( wrapper.vm as any ).getSchema() as Schema;

			expect( schema.getName() ).toBe( NEW_SCHEMA_NAME );
			expect( schema.getDescription() ).toBe( 'What this schema is for' );
		} );

		it( 'returns the property definitions made in the schema editor', async () => {
			editorSchema = newSchema( { title: '', description: '', properties: new PropertyDefinitionList( [ newTextProperty( { name: 'Founded' } ) ] ) } );
			const wrapper = mountComponent();

			await wrapper.find( '.cdx-text-input-stub' ).setValue( NEW_SCHEMA_NAME );

			const schema = ( wrapper.vm as any ).getSchema() as Schema;
			expect( [ ...schema.getPropertyDefinitions() ].map( ( property ) => property.name.toString() ) ).toEqual( [ 'Founded' ] );
		} );

		it( 'returns an empty description when the field is empty, even where the editor holds one', async () => {
			editorSchema = newSchema( { title: '', description: 'The description of the Schema duplicated', properties: new PropertyDefinitionList( [] ) } );
			const wrapper = mountComponent();

			const nameInput = wrapper.find( '.cdx-text-input-stub' );
			await nameInput.setValue( NEW_SCHEMA_NAME );
			await flushPromises();

			expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getDescription() ).toBe( '' );
		} );
	} );

	it( 'gives its name field the place the editor keeps for it, before the Schema\'s labels', () => {
		const wrapper = mountComponent();

		expect( wrapper.find( '.name-slot .cdx-text-input-stub' ).exists() ).toBe( true );
	} );

	it( 'starts a duplicate without the labels of the original, which name that Schema', () => {
		const wrapper = mountComponent( { duplicateOf: newSchema( { title: EXISTING_SCHEMA_NAME, labels: { en: 'Human' } } ) } );

		const startingSchema = wrapper.findComponent( SchemaEditorStub ).props( 'initialSchema' ) as Schema;

		expect( startingSchema.getLabels() ).toBeUndefined();
	} );

	it( 'creates the Schema with the labels given in the editor', async () => {
		editorSchema = newSchema( { title: '', labels: { en: 'Firm' } } );
		const wrapper = mountComponent();

		await wrapper.find( '.cdx-text-input-stub' ).setValue( NEW_SCHEMA_NAME );

		expect( ( ( wrapper.vm as any ).getSchema() as Schema ).getLabels() ).toEqual( { en: 'Firm' } );
	} );

	describe( 'saveBlocker', () => {
		it( 'reports nothing while the schema editor reports nothing', () => {
			const wrapper = mountComponent();

			expect( ( wrapper.vm as any ).saveBlocker() ).toBeNull();
		} );

		it( 'forwards what the schema editor reports', () => {
			editorSaveBlocker = { propertyName: 'Score', message: 'neowiki-field-invalid-number' };

			const wrapper = mountComponent();

			expect( ( wrapper.vm as any ).saveBlocker() ).toEqual( {
				propertyName: 'Score',
				message: 'neowiki-field-invalid-number',
			} );
		} );
	} );

	describe( 'whether its Subjects are dependent', () => {
		const DEPENDENT_CHECKBOX = '.ext-neowiki-schema-creator__dependence input[type="checkbox"]';

		async function createdSchema( wrapper: VueWrapper ): Promise<Schema> {
			await wrapper.find( '.cdx-text-input-stub' ).setValue( NEW_SCHEMA_NAME );

			return ( wrapper.vm as any ).getSchema() as Schema;
		}

		it( 'creates a Schema whose Subjects are standalone unless the user declares them dependent', async () => {
			const wrapper = mountComponent();

			expect( ( await createdSchema( wrapper ) ).subjectsAreDependent() ).toBe( false );
		} );

		it( 'creates a Schema whose Subjects are dependent once the user declares them so', async () => {
			const wrapper = mountComponent();

			await wrapper.find( DEPENDENT_CHECKBOX ).setValue( true );

			expect( ( await createdSchema( wrapper ) ).subjectsAreDependent() ).toBe( true );
		} );

		it( 'tells the schema editor what the user declares', async () => {
			const wrapper = mountComponent();

			await wrapper.find( DEPENDENT_CHECKBOX ).setValue( true );

			expect( wrapper.findComponent( SchemaEditorStub ).props( 'subjectsAreDependent' ) ).toBe( true );
		} );

		it( 'starts a duplicate with what the duplicated Schema declares', () => {
			const wrapper = mountComponent( { duplicateOf: newSchema( { title: 'Birth', description: '', properties: new PropertyDefinitionList( [] ), subjectsAreDependent: true } ) } );

			expect( ( wrapper.find( DEPENDENT_CHECKBOX ).element as HTMLInputElement ).checked ).toBe( true );
		} );

		it( 'offers no choice decided for the user, and creates the Schema as decided', async () => {
			const wrapper = mountComponent( { subjectsAreDependent: true } );

			expect( wrapper.find( DEPENDENT_CHECKBOX ).exists() ).toBe( false );
			expect( ( await createdSchema( wrapper ) ).subjectsAreDependent() ).toBe( true );
		} );

		it( 'says that a Schema decided for the user has dependent Subjects', () => {
			const wrapper = mountComponent( { subjectsAreDependent: true } );

			expect( wrapper.text() ).toContain( 'neowiki-schema-subjects-are-dependent' );
		} );

		function explanationToggle( wrapper: VueWrapper ): DOMWrapper<HTMLButtonElement> {
			const toggle = wrapper.findAll<HTMLButtonElement>( '.ext-neowiki-schema-creator__dependence button' )
				.find( ( button ) => accessibleName( button.element ) === 'neowiki-schema-creator-dependent-info-label' );

			if ( toggle === undefined ) {
				throw new Error( 'No button named after what the explanation explains' );
			}

			return toggle;
		}

		// Whatever element holds it, as long as it is shown.
		function explanationShown( wrapper: VueWrapper ): boolean {
			return wrapper.findAll( '.ext-neowiki-schema-creator__dependence *' )
				.some( ( element ) => element.text() === 'neowiki-schema-creator-dependent-info' && element.isVisible() );
		}

		it( 'keeps its explanation out of sight until asked for it', () => {
			const wrapper = mountComponent();

			expect( explanationShown( wrapper ) ).toBe( false );
			expect( explanationToggle( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'false' );
		} );

		it( 'shows its explanation once asked for it', async () => {
			const wrapper = mountComponent();

			await explanationToggle( wrapper ).trigger( 'click' );

			expect( explanationShown( wrapper ) ).toBe( true );
			expect( explanationToggle( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'true' );
		} );

		it( 'hides its explanation again when asked a second time', async () => {
			const wrapper = mountComponent();

			await explanationToggle( wrapper ).trigger( 'click' );
			await explanationToggle( wrapper ).trigger( 'click' );

			expect( explanationShown( wrapper ) ).toBe( false );
			expect( explanationToggle( wrapper ).attributes( 'aria-expanded' ) ).toBe( 'false' );
		} );

		it( 'names its checkbox by the label alone, also while the explanation shows', async () => {
			const wrapper = mountComponent();

			await explanationToggle( wrapper ).trigger( 'click' );

			expect( accessibleName( wrapper.find( DEPENDENT_CHECKBOX ).element ) ).toBe( 'neowiki-schema-creator-dependent' );
		} );

		it( 'says nothing of a Schema decided for the user to have standalone Subjects', () => {
			const wrapper = mountComponent( { subjectsAreDependent: false } );

			expect( wrapper.find( DEPENDENT_CHECKBOX ).exists() ).toBe( false );
			expect( wrapper.text() ).not.toContain( 'neowiki-schema-subjects-are-dependent' );
		} );
	} );

	it( 'reveals the errors of the schema editor when asked to', () => {
		const wrapper = mountComponent();

		( wrapper.vm as any ).revealErrors();

		expect( editorErrorsRevealed ).toBe( true );
	} );

	it( 'reports a change when the description is typed, so the dialog can offer to save', async () => {
		const wrapper = mountComponent();

		await wrapper.find( '.cdx-text-area-stub' ).setValue( 'Something worth saving' );

		expect( wrapper.emitted( 'change' ) ).toBeTruthy();
	} );

	it( 'emits change when name is typed', async () => {
		const wrapper = mountComponent();

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		await nameInput.setValue( 'A' );

		expect( wrapper.emitted( 'change' ) ).toBeTruthy();
	} );

	it( 'starts with the name it is given', () => {
		const wrapper = mountComponent( { name: 'Artwork' } );

		const nameInput = wrapper.find( '.cdx-text-input-stub' );
		expect( ( nameInput.element as HTMLInputElement ).value ).toBe( 'Artwork' );
	} );
} );
