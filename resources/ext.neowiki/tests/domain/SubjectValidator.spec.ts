import { describe, expect, it } from 'vitest';
import { SubjectValidator } from '@/domain/SubjectValidator';
import { BasePropertyType, PropertyTypeRegistry, ValueValidationError } from '@/domain/PropertyType';
import { Subject } from '@/domain/Subject';
import { Schema } from '@/domain/Schema';
import { StatementList } from '@/domain/StatementList';
import { Statement } from '@/domain/Statement';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, Value, ValueType } from '@/domain/Value';
import { newSubject } from '@/TestHelpers';

describe( 'SubjectValidator', () => {

	const exampleProperty: string = 'exampleProperty';

	class MockPropertyType extends BasePropertyType<PropertyDefinition, Value> {

		public static readonly valueType = ValueType.String;

		public static readonly typeName = 'mock-type';

		public constructor(
			private readonly shouldBeValid: boolean = true,
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

	function getFormatRegistryWithMockPropertyType( isValid: boolean ): PropertyTypeRegistry {
		const registry = new PropertyTypeRegistry();
		registry.registerType( new MockPropertyType( isValid ) );
		return registry;
	}

	function newSchema( propertyNames: string[] ): Schema {
		return new Schema(
			'test-schema',
			'Test schema',
			new PropertyDefinitionList(
				propertyNames.map( ( name ) => ( {
					name: new PropertyName( name ),
					type: 'mock-type',
					description: '',
					required: false,
				} ) ),
			),
		);
	}

	function newValidSubjectWithProperty(): Subject {
		return newSubject( {
			statements: new StatementList( [
				new Statement(
					new PropertyName( exampleProperty ),
					'mock-type',
					newStringValue( 'test' ),
				),
			] ),
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
				getFormatRegistryWithMockPropertyType( true ),
			);

			const subject = newValidSubjectWithProperty();
			const schema = newSchema( [] ); // Property not defined in schema

			expect( validator.validate( subject, schema ) ).toBe( true );
		} );

		it( 'returns true when all statements are valid according to their property types', () => {
			const validator = new SubjectValidator(
				getFormatRegistryWithMockPropertyType( true ),
			);

			const subject = newValidSubjectWithProperty();
			const schema = newSchema( [ exampleProperty ] );

			expect( validator.validate( subject, schema ) ).toBe( true );
		} );

		it( 'returns false when a statement is invalid according to its property type', () => {
			const validator = new SubjectValidator(
				getFormatRegistryWithMockPropertyType( false ),
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
