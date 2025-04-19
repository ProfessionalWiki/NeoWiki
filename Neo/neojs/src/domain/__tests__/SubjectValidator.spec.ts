import { describe, expect, it } from 'vitest';
import { SubjectValidator } from '@neo/domain/SubjectValidator';
import { BasePropertyType, PropertyTypeRegistry, ValueValidationError } from '@neo/domain/PropertyType';
import { Subject } from '@neo/domain/Subject';
import { Schema } from '@neo/domain/Schema';
import { StatementList } from '@neo/domain/StatementList';
import { Statement } from '@neo/domain/Statement';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList';
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue, Value, ValueType } from '@neo/domain/Value';
import { newSubject } from '@neo/TestHelpers';

describe( 'SubjectValidator', () => {

	const exampleProperty: string = 'exampleProperty';

	class MockValueFormat extends BasePropertyType<PropertyDefinition, Value> {

		public static readonly valueType = ValueType.String;

		public static readonly typeName = 'mock-format';

		public constructor(
			private readonly shouldBeValid: boolean = true
		) {
			super();
		}

		public createPropertyDefinitionFromJson(): PropertyDefinition {
			throw new Error( 'Not implemented' );
		}

		public getExampleValue(): Value {
			throw new Error( 'Not implemented' );
		}

		public validate(): ValueValidationError[] {
			return this.shouldBeValid ? [] : [ { code: 'mock-error' } ];
		}

	}

	function getFormatRegistryWithMockFormat( isValid: boolean ): PropertyTypeRegistry {
		const registry = new PropertyTypeRegistry();
		registry.registerType( new MockValueFormat( isValid ) );
		return registry;
	}

	function newSchema( propertyNames: string[] ): Schema {
		return new Schema(
			'test-schema',
			'Test schema',
			new PropertyDefinitionList(
				propertyNames.map( ( name ) => ( {
					name: new PropertyName( name ),
					type: 'mock-format',
					description: '',
					required: false
				} ) )
			)
		);
	}

	function newValidSubjectWithProperty(): Subject {
		return newSubject( {
			statements: new StatementList( [
				new Statement(
					new PropertyName( exampleProperty ),
					'mock-format',
					newStringValue( 'test' )
				)
			] )
		} );
	}

	describe( 'validate', () => {
		it( 'returns true when subject has no statements', () => {
			const validator = new SubjectValidator( new PropertyTypeRegistry() );

			const subject = newSubject();
			const schema = newSchema( [] );

			expect( validator.validate( subject, schema ) ).toBe( true );
		} );

		it( 'returns true when statements are for unknown properties', () => {
			const validator = new SubjectValidator(
				getFormatRegistryWithMockFormat( true )
			);

			const subject = newValidSubjectWithProperty();
			const schema = newSchema( [] ); // Property not defined in schema

			expect( validator.validate( subject, schema ) ).toBe( true );
		} );

		it( 'returns true when all statements are valid according to their property types', () => {
			const validator = new SubjectValidator(
				getFormatRegistryWithMockFormat( true )
			);

			const subject = newValidSubjectWithProperty();
			const schema = newSchema( [ exampleProperty ] );

			expect( validator.validate( subject, schema ) ).toBe( true );
		} );

		it( 'returns false when a statement is invalid according to its property type', () => {
			const validator = new SubjectValidator(
				getFormatRegistryWithMockFormat( false )
			);

			const subject = newValidSubjectWithProperty();
			const schema = newSchema( [ exampleProperty ] );

			expect( validator.validate( subject, schema ) ).toBe( false );
		} );

		it( 'returns false when subject label is empty', () => {
			const validator = new SubjectValidator( new PropertyTypeRegistry() );

			const subject = newSubject( { label: '' } );

			expect( validator.validate( subject, newSchema( [] ) ) ).toBe( false );
		} );

		it( 'returns false when subject label contains only whitespace', () => {
			const validator = new SubjectValidator( new PropertyTypeRegistry() );

			const subject = newSubject( { label: '   ' } );

			expect( validator.validate( subject, newSchema( [] ) ) ).toBe( false );
		} );

	} );
} );
