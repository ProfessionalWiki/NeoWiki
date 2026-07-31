import { describe, expect, it } from 'vitest';
import { extractSeverities, applySeverities } from '@/domain/SeverityNormalizer';
import type { Severity } from '@/domain/Severity';

describe( 'extractSeverities', () => {
	it( 'passes a shorthand scalar through with no severity', () => {
		const [ values, severities ] = extractSeverities( { maximum: 100 } );

		expect( values ).toEqual( { maximum: 100 } );
		expect( severities ).toEqual( {} );
	} );

	it( 'unwraps an object-form scalar', () => {
		const [ values, severities ] = extractSeverities(
			{ maximum: { value: 100, severity: 'error' } },
		);

		expect( values ).toEqual( { maximum: 100 } );
		expect( severities ).toEqual( { maximum: 'error' } );
	} );

	it( 'treats the value-less object form as true', () => {
		const [ values, severities ] = extractSeverities(
			{ required: { severity: 'error' } },
		);

		expect( values ).toEqual( { required: true } );
		expect( severities ).toEqual( { required: 'error' } );
	} );

	it( 'does not normalize reserved keys carrying a severity-shaped value', () => {
		// 'high' is not a severity: normalizing this key would throw rather than pass it through.
		const raw = { type: 'number', description: 'x', default: { severity: 'high' } };

		const [ values, severities ] = extractSeverities( raw );

		expect( values ).toEqual( raw );
		expect( severities ).toEqual( {} );
	} );

	it( 'throws on an unknown severity', () => {
		expect( () => extractSeverities( { maximum: { value: 1, severity: 'bogus' } } ) )
			.toThrow( 'Invalid severity: "bogus"' );
	} );

	// Materially reachable only via the custom keys of unregistered types: the
	// registered type parsers normalize null bounds to undefined downstream.
	it( 'preserves an explicit null value rather than coalescing it to true', () => {
		const [ values ] = extractSeverities(
			{ maximum: { value: null, severity: 'error' } },
		);

		expect( values.maximum ).toBeNull();
	} );

	it( 'records a warning annotation so a later value edit keeps it', () => {
		const [ values, severities ] = extractSeverities(
			{ minimum: { value: 5, severity: 'warning' } },
		);

		expect( values ).toEqual( { minimum: 5 } );
		expect( severities ).toEqual( { minimum: 'warning' } );
	} );
} );

describe( 'applySeverities', () => {
	it( 'wraps an error-annotated scalar and keeps warning as shorthand', () => {
		const severities: Record<string, Severity> = { minimum: 'warning', maximum: 'error' };

		expect( applySeverities( { minimum: 0, maximum: 100 }, severities ) ).toEqual(
			{ minimum: 0, maximum: { value: 100, severity: 'error' } },
		);
	} );

	it( 'wraps an error-annotated true without a value key', () => {
		expect( applySeverities( { required: true }, { required: 'error' } ) ).toEqual(
			{ required: { severity: 'error' } },
		);
	} );

	it( 'keeps a false boolean value rather than implying true', () => {
		// The value-less object form implies true, so emitting it for a `false` value
		// would read back as `true` and silently enable a Constraint the author
		// disabled. Core booleans are always true when annotated, but a custom
		// Property Type's own boolean key carries no such guard.
		expect( applySeverities( { caseSensitive: false }, { caseSensitive: 'error' } ) ).toEqual(
			{ caseSensitive: { value: false, severity: 'error' } },
		);
	} );

	it( 'skips severities whose key is absent or undefined in the JSON', () => {
		expect( applySeverities(
			{ minimum: undefined },
			{ minimum: 'error', maximum: 'error' },
		) ).toEqual( {} );
	} );

	it( 'drops the annotation of an unchecked core boolean Constraint instead of wrapping false', () => {
		expect( applySeverities(
			{ required: false, uniqueItems: false, maximum: 100 },
			{ required: 'error', uniqueItems: 'error', maximum: 'error' },
		) ).toEqual( { required: false, uniqueItems: false, maximum: { value: 100, severity: 'error' } } );
	} );

	it( 'round-trips an options-array annotation through extract', () => {
		const original = { options: { value: [ 'a', 'b' ], severity: 'error' } };

		const [ values, severities ] = extractSeverities( original );

		expect( applySeverities( values, severities ) ).toEqual( original );
	} );
} );
