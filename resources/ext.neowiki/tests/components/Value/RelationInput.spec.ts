import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import { CdxField, CdxIcon } from '@wikimedia/codex';
import RelationInput from '@/components/Value/RelationInput.vue';
import SubjectPicker from '@/components/common/SubjectPicker.vue';
import NeoMultiLookupInput from '@/components/common/NeoMultiLookupInput.vue';
import { RelationValue, newRelation } from '@/domain/Value';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import { RelationTargetCreationKey, RelationTargetCreator, RelationTargetEditingKey, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { NeoWikiTestServices } from '../../NeoWikiTestServices';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers';

const SubjectPickerWithSlots = {
	props: {
		selected: { type: String, default: null },
		targetSchema: { type: String, default: '' },
		// A Codex icon is a path string, or an object carrying its language and direction variants.
		startIcon: { type: [ String, Object ], default: undefined },
		status: { type: String, default: 'default' },
		ariaLabel: { type: String, default: '' },
	},
	template: '<div><slot name="suffix" :selected="selected"></slot></div>',
};

/**
 * Renders the real NeoMultiLookupInput's #input scoped slot (value, onUpdate, onBlur, onFocus,
 * status, ariaLabel) for each modelValue entry, plus a trailing null row — mirroring the always-one-
 * empty-row behaviour of the real component closely enough to test RelationInput's per-row suffix
 * wiring without depending on NeoMultiLookupInput's own normalization logic.
 */
const NeoMultiLookupInputWithSlots = {
	props: {
		modelValue: { type: Array, default: () => [] },
		label: { type: String, default: '' },
	},
	template: '<div>' +
		'<div v-for="( value, index ) in [ ...modelValue, null ]" :key="index">' +
			'<slot ' +
				'name="input" ' +
				':value="value" ' +
				':on-update="() => {}" ' +
				':on-blur="() => {}" ' +
				':on-focus="() => {}" ' +
				'status="default" ' +
				':aria-label="label + \' item \' + ( index + 1 )"' +
			'></slot>' +
		'</div>' +
	'</div>',
};

describe( 'RelationInput', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'message' ] } );
	} );

	function newWrapper( props: Partial<ValueInputProps<RelationProperty>> = {} ): VueWrapper {
		return mount( RelationInput, {
			props: {
				modelValue: undefined,
				label: 'Test Relation',
				property: newRelationProperty( { targetSchema: 'Company' } ),
				...props,
			},
			global: {
				provide: NeoWikiTestServices.getServices(),
				directives: { tooltip: {} },
				mocks: { $i18n: createI18nMock() },
				stubs: {
					SubjectPicker: true,
					NeoMultiLookupInput: true,
				},
			},
		} );
	}

	describe( 'rendering', () => {
		it( 'renders SubjectPicker for single mode', () => {
			const wrapper = newWrapper();

			expect( wrapper.findComponent( SubjectPicker ).exists() ).toBe( true );
			expect( wrapper.findComponent( NeoMultiLookupInput ).exists() ).toBe( false );
		} );

		it( 'renders NeoMultiLookupInput for multiple mode', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { multiple: true } ),
			} );

			expect( wrapper.findComponent( NeoMultiLookupInput ).exists() ).toBe( true );
			expect( wrapper.findComponent( SubjectPicker ).exists() ).toBe( false );
		} );

		it( 'renders description icon when property has description', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { description: 'Pick a company' } ),
			} );

			expect( wrapper.findComponent( CdxIcon ).exists() ).toBe( true );
		} );

		it( 'does not render description icon without description', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { description: '' } ),
			} );

			expect( wrapper.findComponent( CdxIcon ).exists() ).toBe( false );
		} );
	} );

	describe( 'single mode', () => {
		it( 'passes subject ID from initial value to SubjectPicker', () => {
			const wrapper = newWrapper( {
				modelValue: new RelationValue( [ newRelation( undefined, 's1demo1aaaaaaa1' ) ] ),
			} );

			expect( wrapper.findComponent( SubjectPicker ).props( 'selected' ) ).toBe( 's1demo1aaaaaaa1' );
		} );

		it( 'passes null when no initial value', () => {
			const wrapper = newWrapper();

			expect( wrapper.findComponent( SubjectPicker ).props( 'selected' ) ).toBeNull();
		} );

		it( 'passes targetSchema to SubjectPicker', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { targetSchema: 'Person' } ),
			} );

			expect( wrapper.findComponent( SubjectPicker ).props( 'targetSchema' ) ).toBe( 'Person' );
		} );

		it( 'emits RelationValue when subject is selected', async () => {
			const wrapper = newWrapper();

			wrapper.findComponent( SubjectPicker ).vm.$emit( 'update:selected', 's1demo1aaaaaaa1' );
			await wrapper.vm.$nextTick();

			const emitted = wrapper.emitted( 'update:modelValue' )!;
			expect( emitted ).toHaveLength( 1 );

			const value = emitted[ 0 ][ 0 ] as RelationValue;
			expect( value.relations ).toHaveLength( 1 );
			expect( value.relations[ 0 ].target.text ).toBe( 's1demo1aaaaaaa1' );
		} );

		it( 'emits undefined when selection is cleared', async () => {
			const wrapper = newWrapper( {
				modelValue: new RelationValue( [ newRelation( undefined, 's1demo1aaaaaaa1' ) ] ),
			} );

			wrapper.findComponent( SubjectPicker ).vm.$emit( 'update:selected', null );
			await wrapper.vm.$nextTick();

			expect( wrapper.emitted( 'update:modelValue' )![ 0 ][ 0 ] ).toBeUndefined();
		} );

		it( 'hides validation error before blur for required empty field', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { required: true } ),
			} );
			const field = wrapper.findComponent( CdxField );

			expect( field.props( 'messages' ) ).toEqual( {} );
			expect( field.props( 'status' ) ).toBe( 'default' );
		} );

		it( 'passes field status to SubjectPicker', () => {
			const clean = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
			} );
			expect( clean.findComponent( SubjectPicker ).props( 'status' ) ).toBe( 'default' );

			const withViolation = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{ propertyName: 'Owner', code: 'required', args: [], severity: 'error', valuePartIndex: null },
				],
			} );
			expect( withViolation.findComponent( SubjectPicker ).props( 'status' ) ).toBe( 'error' );
		} );

		it( 'suppresses required error when SubjectPicker reports unmatched text', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { required: true } ),
			} );

			wrapper.findComponent( SubjectPicker ).vm.$emit( 'blur', true );
			await wrapper.vm.$nextTick();

			const field = wrapper.findComponent( CdxField );
			expect( field.props( 'messages' ) ).toEqual( {} );
			expect( field.props( 'status' ) ).toBe( 'default' );
		} );

		it( 'surfaces a server violation on an untouched single relation', () => {
			const wrapper = newWrapper( {
				// The message can only originate from the server-sourced violation.
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{ propertyName: 'Owner', code: 'type-mismatch', args: [], severity: 'error', valuePartIndex: null },
				],
			} );

			const field = wrapper.findComponent( CdxField );
			expect( field.props( 'messages' ) ).toEqual( { error: 'neowiki-field-type-mismatch' } );
			expect( field.props( 'status' ) ).toBe( 'error' );
		} );

		it( 'keeps a server violation suppressed while the lookup reports unmatched text', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{ propertyName: 'Owner', code: 'type-mismatch', args: [], severity: 'error', valuePartIndex: null },
				],
			} );

			wrapper.findComponent( SubjectPicker ).vm.$emit( 'blur', true );
			await wrapper.vm.$nextTick();

			const field = wrapper.findComponent( CdxField );
			expect( field.props( 'messages' ) ).toEqual( {} );
			expect( field.props( 'status' ) ).toBe( 'default' );
		} );
	} );

	describe( 'target editing', () => {
		function mountSingleWithTarget( targetEditing: boolean ): VueWrapper {
			return mount( RelationInput, {
				props: {
					modelValue: new RelationValue( [ newRelation( undefined, 's11111111111111' ) ] ),
					property: newRelationProperty( { multiple: false } ),
					label: 'Author',
				},
				global: {
					provide: {
						...NeoWikiTestServices.getServices(),
						[ RelationTargetEditingKey as symbol ]: targetEditing,
					},
					directives: { tooltip: {} },
					mocks: { $i18n: createI18nMock() },
					stubs: { SubjectPicker: SubjectPickerWithSlots, NeoMultiLookupInput: true },
				},
			} );
		}

		it( 'shows an edit button for the selected target when target editing is enabled', () => {
			const wrapper = mountSingleWithTarget( true );
			expect( wrapper.find( '.ext-neowiki-relation-input__edit-target' ).exists() ).toBe( true );
		} );

		it( 'emits edit-relation-target with the target SubjectId on click', async () => {
			const wrapper = mountSingleWithTarget( true );
			await wrapper.find( '.ext-neowiki-relation-input__edit-target' ).trigger( 'click' );

			const emitted = wrapper.emitted( 'edit-relation-target' );
			expect( emitted ).toHaveLength( 1 );
			expect( ( emitted![ 0 ][ 0 ] as SubjectId ).text ).toBe( 's11111111111111' );
		} );

		it( 'shows no edit button when target editing was not enabled by the host', () => {
			const wrapper = mountSingleWithTarget( false );
			expect( wrapper.find( '.ext-neowiki-relation-input__edit-target' ).exists() ).toBe( false );
		} );

		describe( 'multiple mode', () => {
			function mountMultipleWithTarget( targetEditing: boolean ): VueWrapper {
				return mount( RelationInput, {
					props: {
						modelValue: new RelationValue( [ newRelation( undefined, 's11111111111111' ) ] ),
						property: newRelationProperty( { multiple: true } ),
						label: 'Authors',
					},
					global: {
						provide: {
							...NeoWikiTestServices.getServices(),
							[ RelationTargetEditingKey as symbol ]: targetEditing,
						},
						directives: { tooltip: {} },
						mocks: { $i18n: createI18nMock() },
						stubs: { SubjectPicker: SubjectPickerWithSlots, NeoMultiLookupInput: NeoMultiLookupInputWithSlots },
					},
				} );
			}

			it( 'shows an edit button only for the row with a selected target, not the trailing empty row', () => {
				const wrapper = mountMultipleWithTarget( true );

				// The stub renders one row for the selected target plus a trailing null row, mirroring
				// NeoMultiLookupInput's always-one-empty-row behaviour.
				expect( wrapper.findAll( '.ext-neowiki-relation-input__edit-target' ) ).toHaveLength( 1 );
			} );

			it( 'emits edit-relation-target with that row\'s SubjectId on click', async () => {
				const wrapper = mountMultipleWithTarget( true );
				await wrapper.find( '.ext-neowiki-relation-input__edit-target' ).trigger( 'click' );

				const emitted = wrapper.emitted( 'edit-relation-target' );
				expect( emitted ).toHaveLength( 1 );
				expect( ( emitted![ 0 ][ 0 ] as SubjectId ).text ).toBe( 's11111111111111' );
			} );

			it( 'shows no edit button when target editing was not enabled by the host', () => {
				const wrapper = mountMultipleWithTarget( false );
				expect( wrapper.find( '.ext-neowiki-relation-input__edit-target' ).exists() ).toBe( false );
			} );
		} );
	} );

	describe( 'target creation', () => {
		const createdSubject = new Subject(
			new SubjectId( 's22222222222222' ),
			'Acme Inc.',
			'Acme Inc.',
			'Person',
			new StatementList( [] ),
		);

		function newCreatorReturning( subject: Subject | null ): Mock<RelationTargetCreator> {
			return vi.fn<RelationTargetCreator>().mockResolvedValue( subject );
		}

		/**
		 * Leaves NeoMultiLookupInput real, so the per-row pickers of a multiple relation are the
		 * ones RelationInput actually renders into its #input slot.
		 */
		function mountRelationInput(
			property: RelationProperty,
			provide: Record<symbol, unknown>,
		): VueWrapper {
			return mount( RelationInput, {
				props: {
					modelValue: undefined,
					property: property,
					label: 'Author',
				},
				global: {
					provide: { ...NeoWikiTestServices.getServices(), ...provide },
					directives: { tooltip: {} },
					mocks: { $i18n: createI18nMock() },
					stubs: { SubjectPicker: true },
				},
			} );
		}

		function mountSingleWithCreator( creator: RelationTargetCreator ): VueWrapper {
			return mountRelationInput(
				newRelationProperty( { targetSchema: 'Person' } ),
				{ [ RelationTargetCreationKey as symbol ]: creator },
			);
		}

		function mountSingleWithoutCreator(): VueWrapper {
			return mountRelationInput( newRelationProperty( { targetSchema: 'Person' } ), {} );
		}

		function mountMultipleWithCreator( creator: RelationTargetCreator ): VueWrapper {
			return mountRelationInput(
				newRelationProperty( { targetSchema: 'Person', multiple: true } ),
				{ [ RelationTargetCreationKey as symbol ]: creator },
			);
		}

		function mountMultipleWithoutCreator(): VueWrapper {
			return mountRelationInput(
				newRelationProperty( { targetSchema: 'Person', multiple: true } ),
				{},
			);
		}

		function createFunctionOfPicker( picker: VueWrapper ): ( label: string | null ) => Promise<Subject | null> {
			return createSubjectPropOf( picker ) as ( label: string | null ) => Promise<Subject | null>;
		}

		// The pickers are auto-stubbed, and a stub's props type as `never`; read the prop as the
		// untyped bag it is rather than pretend the stub carries SubjectPicker's own types.
		function createSubjectPropOf( picker: VueWrapper ): unknown {
			return ( picker.props() as Record<string, unknown> ).createSubject;
		}

		function firstPicker( wrapper: VueWrapper ): VueWrapper {
			return wrapper.findAllComponents( SubjectPicker )[ 0 ] as unknown as VueWrapper;
		}

		function targetsOfLastUpdate( wrapper: VueWrapper ): string[] {
			const emitted = wrapper.emitted( 'update:modelValue' )!;
			const value = emitted[ emitted.length - 1 ][ 0 ] as RelationValue;
			return value.relations.map( ( relation ) => relation.target.text );
		}

		it( 'gives the picker no create function when the host cannot create targets', () => {
			const wrapper = mountSingleWithoutCreator();

			expect( createSubjectPropOf( wrapper.findComponent( SubjectPicker ) as unknown as VueWrapper ) ).toBeUndefined();
		} );

		it( 'gives the picker a create function when the host can create targets', () => {
			const wrapper = mountSingleWithCreator( newCreatorReturning( createdSubject ) );

			expect( typeof createSubjectPropOf( wrapper.findComponent( SubjectPicker ) as unknown as VueWrapper ) ).toBe( 'function' );
		} );

		it( 'creates a target of the property\'s target schema, labelled with the typed text', async () => {
			const creator = newCreatorReturning( createdSubject );
			const wrapper = mountSingleWithCreator( creator );

			await createFunctionOfPicker( wrapper.findComponent( SubjectPicker ) )( 'Acme Inc.' );

			// The picker never learns the schema, so a target of the wrong type would be created
			// were the property's own targetSchema not the one bound here.
			expect( creator ).toHaveBeenCalledWith( 'Person', 'Acme Inc.' );
		} );

		it( 'asks for an unlabelled target when the picker held no text', async () => {
			const creator = newCreatorReturning( createdSubject );
			const wrapper = mountSingleWithCreator( creator );

			await createFunctionOfPicker( wrapper.findComponent( SubjectPicker ) )( null );

			expect( creator ).toHaveBeenCalledWith( 'Person', null );
		} );

		it( 'hands the created Subject back to the picker', async () => {
			const wrapper = mountSingleWithCreator( newCreatorReturning( createdSubject ) );

			const subject = await createFunctionOfPicker( wrapper.findComponent( SubjectPicker ) )( 'Acme Inc.' );

			// The picker fills its input from this Subject, so anything else leaves the field
			// naming a target the user cannot see.
			expect( subject ).toBe( createdSubject );
		} );

		it( 'reports the host refusing to create by resolving to null', async () => {
			const wrapper = mountSingleWithCreator( newCreatorReturning( null ) );

			const subject = await createFunctionOfPicker( wrapper.findComponent( SubjectPicker ) )( 'Acme Inc.' );

			expect( subject ).toBeNull();
		} );

		it( 'emits a RelationValue targeting the Subject the picker created', async () => {
			const wrapper = mountSingleWithCreator( newCreatorReturning( createdSubject ) );

			wrapper.findComponent( SubjectPicker ).vm.$emit( 'update:selected', 's22222222222222' );
			await flushPromises();

			expect( targetsOfLastUpdate( wrapper ) ).toEqual( [ 's22222222222222' ] );
		} );

		describe( 'multiple mode', () => {
			it( 'gives each row picker no create function when the host cannot create targets', () => {
				const wrapper = mountMultipleWithoutCreator();

				expect( createSubjectPropOf( firstPicker( wrapper ) ) ).toBeUndefined();
			} );

			it( 'creates a target of the property\'s target schema from a row', async () => {
				const creator = newCreatorReturning( createdSubject );
				const wrapper = mountMultipleWithCreator( creator );

				await createFunctionOfPicker( firstPicker( wrapper ) )( 'Acme Inc.' );

				expect( creator ).toHaveBeenCalledWith( 'Person', 'Acme Inc.' );
			} );

			it( 'emits a RelationValue targeting the Subject created in a row', async () => {
				const wrapper = mountMultipleWithCreator( newCreatorReturning( createdSubject ) );

				firstPicker( wrapper ).vm.$emit( 'update:selected', 's22222222222222' );
				await flushPromises();

				expect( targetsOfLastUpdate( wrapper ) ).toEqual( [ 's22222222222222' ] );
			} );
		} );
	} );

	describe( 'multiple mode', () => {
		it( 'passes selected IDs to NeoMultiLookupInput', () => {
			const wrapper = newWrapper( {
				modelValue: new RelationValue( [
					newRelation( undefined, 's1demo1aaaaaaa1' ),
					newRelation( undefined, 's1demo5sssssss1' ),
				] ),
				property: newRelationProperty( { multiple: true } ),
			} );

			expect( wrapper.findComponent( NeoMultiLookupInput ).props( 'modelValue' ) ).toEqual(
				[ 's1demo1aaaaaaa1', 's1demo5sssssss1' ],
			);
		} );

		it( 'passes empty array when no initial value', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { multiple: true } ),
			} );

			expect( wrapper.findComponent( NeoMultiLookupInput ).props( 'modelValue' ) ).toEqual( [] );
		} );

		it( 'emits RelationValue when selections change', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { multiple: true } ),
			} );

			wrapper.findComponent( NeoMultiLookupInput ).vm.$emit(
				'update:modelValue', [ 's1demo1aaaaaaa1', 's1demo5sssssss1' ],
			);
			await wrapper.vm.$nextTick();

			const emitted = wrapper.emitted( 'update:modelValue' )!;
			expect( emitted ).toHaveLength( 1 );

			const value = emitted[ 0 ][ 0 ] as RelationValue;
			expect( value.relations ).toHaveLength( 2 );
			expect( value.relations[ 0 ].target.text ).toBe( 's1demo1aaaaaaa1' );
			expect( value.relations[ 1 ].target.text ).toBe( 's1demo5sssssss1' );
		} );

		it( 'emits undefined when all selections are null', async () => {
			const wrapper = newWrapper( {
				modelValue: new RelationValue( [ newRelation( undefined, 's1demo1aaaaaaa1' ) ] ),
				property: newRelationProperty( { multiple: true } ),
			} );

			wrapper.findComponent( NeoMultiLookupInput ).vm.$emit( 'update:modelValue', [ null ] );
			await wrapper.vm.$nextTick();

			expect( wrapper.emitted( 'update:modelValue' )![ 0 ][ 0 ] ).toBeUndefined();
		} );

		it( 'surfaces a server field-level violation for a multiple relation', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owners', required: true, multiple: true } ),
				serverViolations: [
					{ propertyName: 'Owners', code: 'required', args: [], severity: 'error', valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual(
				{ error: 'neowiki-field-required' },
			);
		} );

		it( 'shows a warning violation with the warning status on a single relation', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{ propertyName: 'Owner', code: 'relation-target-not-found', args: [], severity: 'warning', valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'warning' );
			expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual(
				{ warning: 'neowiki-field-relation-target-not-found' },
			);
		} );

		it( 'keeps field status default for a multiple relation even with a violation', () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owners', required: true, multiple: true } ),
				serverViolations: [
					{ propertyName: 'Owners', code: 'required', args: [], severity: 'error', valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
		} );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns initial value', () => {
			const value = new RelationValue( [ newRelation( undefined, 's1demo1aaaaaaa1' ) ] );
			const wrapper = newWrapper( { modelValue: value } );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toStrictEqual( value );
		} );

		it( 'returns undefined for no value', () => {
			const wrapper = newWrapper();

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );

		it( 'returns updated value after selection', async () => {
			const wrapper = newWrapper();

			wrapper.findComponent( SubjectPicker ).vm.$emit( 'update:selected', 's1demo1aaaaaaa1' );
			await wrapper.vm.$nextTick();

			const currentValue = ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() as RelationValue;
			expect( currentValue.relations ).toHaveLength( 1 );
			expect( currentValue.relations[ 0 ].target.text ).toBe( 's1demo1aaaaaaa1' );
		} );
	} );

	describe( 'clearing server violations', () => {
		it( 'clears a part-indexed violation using its own index', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{
						propertyName: 'Owner',
						code: 'relation-target-schema-mismatch',
						args: [ 'Company', 'Person' ],
						severity: 'error',
						valuePartIndex: 0,
					},
				],
			} );

			wrapper.findComponent( SubjectPicker ).vm.$emit( 'update:selected', 's1demo1aaaaaaa1' );
			await wrapper.vm.$nextTick();

			// The parent drops violations by exact valuePartIndex match, so a null here would
			// never match index 0 and the error would outlive the fix the user just made.
			expect( wrapper.emitted( 'clear-server-violation' ) ).toEqual( [
				[ { propertyName: 'Owner', valuePartIndex: 0 } ],
			] );
		} );

		it( 'clears every violation on the property, one emit per index', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owners', targetSchema: 'Company', multiple: true } ),
				serverViolations: [
					{ propertyName: 'Owners', code: 'relation-target-not-found', args: [ 'x' ], severity: 'error', valuePartIndex: 0 },
					{ propertyName: 'Owners', code: 'relation-target-schema-mismatch', args: [], severity: 'error', valuePartIndex: 1 },
				],
			} );

			wrapper.findComponent( NeoMultiLookupInput ).vm.$emit( 'update:modelValue', [ 's1demo1aaaaaaa1' ] );
			await wrapper.vm.$nextTick();

			// This field shows one aggregate error, so clearing only the displayed violation
			// would strand the rest with nothing on screen to explain them.
			expect( wrapper.emitted( 'clear-server-violation' ) ).toEqual( [
				[ { propertyName: 'Owners', valuePartIndex: 0 } ],
				[ { propertyName: 'Owners', valuePartIndex: 1 } ],
			] );
		} );

		it( 'does not emit when the property has no violation', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{ propertyName: 'Other', code: 'required', args: [], severity: 'error', valuePartIndex: null },
				],
			} );

			wrapper.findComponent( SubjectPicker ).vm.$emit( 'update:selected', 's1demo1aaaaaaa1' );
			await wrapper.vm.$nextTick();

			expect( wrapper.emitted( 'clear-server-violation' ) ).toBeUndefined();
		} );

		it( 'clears a field-level (null-index) violation on the property', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owner', targetSchema: 'Company' } ),
				serverViolations: [
					{ propertyName: 'Owner', code: 'required', args: [], severity: 'error', valuePartIndex: null },
				],
			} );

			wrapper.findComponent( SubjectPicker ).vm.$emit( 'update:selected', 's1demo1aaaaaaa1' );
			await wrapper.vm.$nextTick();

			// A required violation carries valuePartIndex: null; the parent drops by exact match,
			// so the clear must carry null too or the stale error outlives the user's fix.
			expect( wrapper.emitted( 'clear-server-violation' ) ).toEqual( [
				[ { propertyName: 'Owner', valuePartIndex: null } ],
			] );
		} );

		it( 'uses the violation index rather than the array position on a gapped multi', async () => {
			const wrapper = newWrapper( {
				property: newRelationProperty( { name: 'Owners', targetSchema: 'Company', multiple: true } ),
				serverViolations: [
					{ propertyName: 'Owners', code: 'relation-target-not-found', args: [ 'x' ], severity: 'error', valuePartIndex: 0 },
					{ propertyName: 'Owners', code: 'relation-target-schema-mismatch', args: [], severity: 'error', valuePartIndex: 2 },
				],
			} );

			wrapper.findComponent( NeoMultiLookupInput ).vm.$emit( 'update:modelValue', [ 's1demo1aaaaaaa1' ] );
			await wrapper.vm.$nextTick();

			// Gapped indices 0 and 2: a positional counter would emit 0 and 1, stranding index 2.
			expect( wrapper.emitted( 'clear-server-violation' ) ).toEqual( [
				[ { propertyName: 'Owners', valuePartIndex: 0 } ],
				[ { propertyName: 'Owners', valuePartIndex: 2 } ],
			] );
		} );
	} );
} );
