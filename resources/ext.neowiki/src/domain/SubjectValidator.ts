import { PropertyType, PropertyTypeRegistry } from '@/domain/PropertyType';
import { Subject } from '@/domain/Subject';
import { Schema } from '@/domain/Schema';
import { Statement } from '@/domain/Statement';

export class SubjectValidator {

	public constructor(
		private readonly propertyTypeRegistry: PropertyTypeRegistry,
	) {}

	public validate( subject: Subject, schema: Schema ): boolean {
		if ( subject.getLabel().trim() === '' ) {
			return false;
		}

		for ( const statement of subject.getStatements() ) {
			if ( !this.statementIsValid( statement, schema ) ) {
				return false;
			}
		}

		return true;
	}

	private statementIsValid( statement: Statement, schema: Schema ): boolean {
		if ( !schema.getPropertyDefinitions().has( statement.propertyName ) ) {
			return true; // Statements for unknown properties are considered valid
		}

		const errors =
			this.getPropertyType( statement )
				.validate(
					statement.value,
					schema.getPropertyDefinitions().get( statement.propertyName ),
				);

		return errors.length === 0;
	}

	private getPropertyType( statement: Statement ): PropertyType {
		return this.propertyTypeRegistry.getType( statement.propertyType );
	}

}
