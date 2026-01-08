import { Value } from '@/domain/Value';
import { PropertyType } from '@/domain/PropertyType';
import { PropertyDefinition } from '@/domain/PropertyDefinition';
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
