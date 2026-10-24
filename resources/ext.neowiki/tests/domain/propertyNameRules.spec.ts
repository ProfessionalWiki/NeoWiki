import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { type PropertyNameProblem, propertyNameProblem, propertyNameProblemsInSchema } from '@/domain/propertyNameRules';

interface ValidVector {
	case: string;
	name: string;
}

interface InvalidVector {
	case: string;
	name: string;
	problem: Omit<PropertyNameProblem, 'name'>;
}

interface SchemaVector {
	case: string;
	names: string[];
	problems: PropertyNameProblem[];
}

/**
 * The shared vectors in tests/vectors/property-names.json, which the PHP suite runs against the wiki's own rules, so
 * the Schema editor refuses what the wiki refuses.
 */
const vectors = JSON.parse( readFileSync(
	// Vitest runs from resources/ext.neowiki, so the extension root is two levels up.
	resolve( process.cwd(), '../../tests/vectors/property-names.json' ),
	'utf8',
) ) as { valid: ValidVector[]; invalid: InvalidVector[]; schemas: SchemaVector[] };

describe( 'propertyNameRules', () => {

	it.each( vectors.valid )( 'accepts $case', ( vector: ValidVector ) => {
		expect( propertyNameProblem( vector.name ) ).toBeNull();
	} );

	it.each( vectors.invalid )( 'names the first rule broken by $case', ( vector: InvalidVector ) => {
		expect( propertyNameProblem( vector.name ) ).toEqual( { name: vector.name, ...vector.problem } );
	} );

	it.each( vectors.schemas )( 'finds every problem in a Schema with $case', ( vector: SchemaVector ) => {
		expect( propertyNameProblemsInSchema( vector.names ) ).toEqual( vector.problems );
	} );

} );
