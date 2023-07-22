import { describe, expect, it } from 'vitest';
import { newTextProperty, TextFormat } from '../Text';
import { newStringValue } from '../../Value';

describe( 'TextFormat', () => {

	const format = new TextFormat();

	describe( 'getFormatName', () => {

		it( 'returns "text"', () => {
			expect( format.getFormatName() ).toBe( 'text' );
		} );

	} );

	describe( 'formatValueAsHtml', () => {

		it( 'returns empty string for empty list', () => {
			expect( format.formatValueAsHtml(
				newStringValue(),
				newTextProperty()
			) ).toBe( '' );
		} );

		it( 'creates comma separated list for multiple values', () => {
			expect( format.formatValueAsHtml(
				newStringValue( 'foo', 'bar', 'baz' ),
				newTextProperty()
			) ).toBe( 'foo, bar, baz' );
		} );

	} );

} );
