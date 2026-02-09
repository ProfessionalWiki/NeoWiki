import { VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import { newRelationProperty, RelationProperty } from '@/domain/propertyTypes/Relation';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { createTestWrapper } from '../../../VueTestHelpers.ts';

describe( 'RelationAttributesEditor', () => {

	function newWrapper( props: Partial<AttributesEditorProps<RelationProperty>> = {} ): VueWrapper {
		return createTestWrapper( RelationAttributesEditor, {
			property: newRelationProperty( {} ),
			...props,
		} );
	}

	it( 'renders without error', () => {
		const wrapper = newWrapper();

		expect( wrapper.find( '.relation-attributes' ).exists() ).toBe( true );
	} );

} );
