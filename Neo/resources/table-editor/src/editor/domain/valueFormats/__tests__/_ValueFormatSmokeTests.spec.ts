import { test, expect, describe } from 'vitest';
import { NeoWikiExtension } from '../../../../NeoWikiExtension';
import type { ValueFormat } from '../../ValueFormat';
import { newRelationProperty } from '../Relation';

describe( 'All ValueFormat implementations', () => {

	const formats = NeoWikiExtension.getInstance().getValueFormatRegistry().getFormats();

	test.each( formats )( 'getExampleValue should return something', ( format: ValueFormat ) => {
		expect( format.getExampleValue( newRelationProperty( 'Test product' ) ) ).toBeDefined();
	} );

} );
