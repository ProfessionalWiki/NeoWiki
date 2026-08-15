import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import type { SubjectEditorExposes } from '@/components/SubjectEditor/SubjectEditor.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition.ts';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

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

	/**
	 * Puts the number field into the state a browser reports for text it cannot
	 * parse (say "5foo"): the characters stay visible in the widget, the value
	 * reads as empty, and validity.badInput is set. jsdom never sets that flag
	 * from typing, so the browser's report is stubbed here.
	 */
	async function reportBadNumberInput( wrapper: VueWrapper ): Promise<void> {
		const input = wrapper.find( 'input[type="number"]' );
		Object.defineProperty( input.element, 'validity', { value: { badInput: true }, configurable: true } );
		await input.trigger( 'input' );
	}

	it( 'reports no unparseable input while every field can be read', () => {
		const wrapper = newWrapper();

		expect( editor( wrapper ).hasUnparseableInput() ).toBe( false );
	} );

	it( 'reports unparseable input when one of its fields holds text it cannot turn into a value', async () => {
		const wrapper = newWrapper();

		await reportBadNumberInput( wrapper );

		expect( editor( wrapper ).hasUnparseableInput() ).toBe( true );
	} );

	// A dry-run response re-renders the editor; the report lives in the field, so it
	// survives that — but would be lost if fields were remounted rather than patched.
	it( 'keeps reporting unparseable input after a re-render', async () => {
		const wrapper = newWrapper();
		await reportBadNumberInput( wrapper );

		await wrapper.setProps( {
			serverViolations: [
				{ propertyName: 'Name', code: 'required', args: [], severity: 'error', valuePartIndex: null },
			],
		} );

		expect( editor( wrapper ).hasUnparseableInput() ).toBe( true );
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
		await reportBadNumberInput( wrapper );

		const withoutNumber = new Schema( 'TestSchema', 'A test schema', new PropertyDefinitionList( [
			createPropertyDefinitionFromJson( 'Name', { type: TextType.typeName } ),
		] ) );
		await wrapper.setProps( { schema: withoutNumber, statements: withoutNumber.blankStatements() } );

		expect( editor( wrapper ).hasUnparseableInput() ).toBe( false );
	} );
} );
