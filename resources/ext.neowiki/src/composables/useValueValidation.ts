import { Value } from '@neo/domain/Value';
import { PropertyType } from '@neo/domain/PropertyType';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { ValidationMessages } from '@wikimedia/codex';

export function validateValue( value: Value, propertyType: PropertyType, property: PropertyDefinition ): ValidationMessages {
	const validationErrors = propertyType.validate( value, property );

	if ( validationErrors.length > 0 ) {
		return {
			error: mw.message(
				`neowiki-field-${ validationErrors[ 0 ].code }`,
				...( validationErrors[ 0 ].args ?? [] )
			).text()
		};
	}

	return {};
}
