import { ValueFormat, ValueFormatRegistry } from '@neo/domain/ValueFormat';
import { Subject } from '@neo/domain/Subject';
import { Schema } from '@neo/domain/Schema';
import { Statement } from '@neo/domain/Statement';

export class SubjectValidator {

	public constructor(
		private readonly formatRegistry: ValueFormatRegistry
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

		const property = schema.getPropertyDefinitions().get( statement.propertyName );

		if ( property.format !== statement.format ) {
			return false; // Values in the wrong format are considered invalid
		}

		const errors =
			this.getValueFormat( statement )
				.validate( // TODO: maybe we need to verify the statement value matches the statement format
					statement.value,
					property
				);

		return errors.length === 0;
	}

	private getValueFormat( statement: Statement ): ValueFormat {
		return this.formatRegistry.getFormat( statement.format );
	}

}
