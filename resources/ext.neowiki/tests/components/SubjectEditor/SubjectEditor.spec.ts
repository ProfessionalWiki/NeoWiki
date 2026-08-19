import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import type { SubjectEditorExposes } from '@/components/SubjectEditor/SubjectEditor.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { createTestWrapper, reportUnparseableNumber } from '../../VueTestHelpers.ts';

describe( 'SubjectEditor', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( key: string ) => ( {
				text: () => key,
				parse: () => key,
			} ) ),
		} );
	} );

	const schema = new Schema(
		'TestSchema',
		'A test schema',
		new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'Name', { type: TextType.typeName } ),
			createPropertyDefinitionFromJson( 'Score', { type: NumberType.typeName } ),
			// Sits after the number field, so an editor that only consulted the last
			// field would report nothing unparseable.
			createPropertyDefinitionFromJson( 'Notes', { type: TextType.typeName } ),
		] ),
	);

	function newWrapper(): VueWrapper {
		return createTestWrapper( SubjectEditor, {
			statements: schema.blankStatements(),
			schema,
		} );
	}

	function editor( wrapper: VueWrapper ): SubjectEditorExposes {
		return wrapper.vm as unknown as SubjectEditorExposes;
	}

	it( 'reports no unparseable input while every field can be read', () => {
		const wrapper = newWrapper();

		expect( editor( wrapper ).unparseableInput() ).toBeNull();
	} );

	it( 'reports unparseable input when one of its fields holds text it cannot turn into a value', async () => {
		const wrapper = newWrapper();

		await reportUnparseableNumber( wrapper.find( 'input[type="number"]' ) );

		expect( editor( wrapper ).unparseableInput() )
			.toEqual( { propertyName: 'Score', message: 'neowiki-field-invalid-number' } );
	} );

	// A dry-run response re-renders the editor; the report lives in the field, so it
	// survives that — but would be lost if fields were remounted rather than patched.
	it( 'keeps reporting unparseable input after a re-render', async () => {
		const wrapper = newWrapper();
		await reportUnparseableNumber( wrapper.find( 'input[type="number"]' ) );

		await wrapper.setProps( {
			serverViolations: [
				{ propertyName: 'Name', code: 'required', args: [], severity: 'error', valuePartIndex: null },
			],
		} );

		expect( editor( wrapper ).unparseableInput() )
			.toEqual( { propertyName: 'Score', message: 'neowiki-field-invalid-number' } );
	} );

	// The schema can change while the dialog is open (the nested schema editor); a
	// stale reference to a field no longer in the schema must not keep holding saves.
	it( 'stops reporting unparseable input once the field is gone from the schema', async () => {
		const withTrailingNumber = new Schema( 'TestSchema', 'A test schema', new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'Name', { type: TextType.typeName } ),
			createPropertyDefinitionFromJson( 'Score', { type: NumberType.typeName } ),
		] ) );
		const wrapper = createTestWrapper( SubjectEditor, {
			statements: withTrailingNumber.blankStatements(),
			schema: withTrailingNumber,
		} );
		await reportUnparseableNumber( wrapper.find( 'input[type="number"]' ) );

		const withoutNumber = new Schema( 'TestSchema', 'A test schema', new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'Name', { type: TextType.typeName } ),
		] ) );
		await wrapper.setProps( { schema: withoutNumber, statements: withoutNumber.blankStatements() } );

		expect( editor( wrapper ).unparseableInput() ).toBeNull();
	} );
} );
