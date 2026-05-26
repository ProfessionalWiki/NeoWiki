import { PropertyTypeRegistry } from '@/domain/PropertyType';
import type { ValueValidationError } from '@/domain/PropertyType';
import { Subject } from '@/domain/Subject';
import { Schema } from '@/domain/Schema';
import { Statement } from '@/domain/Statement';

export interface SubjectValidationError {
	readonly propertyName: string | null;
	readonly error: ValueValidationError;
}

export class SubjectValidator {

	public constructor(
		private readonly propertyTypeRegistry: PropertyTypeRegistry,
	) {}

	public validate( subject: Subject, schema: Schema ): SubjectValidationError[] {
		const errors: SubjectValidationError[] = [];

		if ( subject.getLabel().trim() === '' ) {
			errors.push( { propertyName: null, error: { code: 'label-required' } } );
		}

		for ( const statement of subject.getStatements() ) {
			errors.push( ...this.validateStatement( statement, schema ) );
		}

		return errors;
	}

	private validateStatement( statement: Statement, schema: Schema ): SubjectValidationError[] {
		const propertyDef = schema.getPropertyDefinitions().get( statement.propertyName );
		if ( propertyDef === undefined ) {
			return [];
		}

		const propertyType = this.propertyTypeRegistry.getType( statement.propertyType );
		const valueErrors = propertyType.validate( statement.value, propertyDef );

		return valueErrors.map( ( error ) => ( { propertyName: statement.propertyName.toString(), error } ) );
	}

}
