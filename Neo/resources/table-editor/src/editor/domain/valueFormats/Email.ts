import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import { createStringFormField, ValidationResult, type ValueFormatInterface } from '@/editor/domain/ValueFormat';

export interface EmailProperty extends MultiStringProperty {
}

export class EmailFormat implements ValueFormatInterface<EmailProperty, StringValue> {

	public readonly valueType = ValueType.String;
	public readonly name = 'email';

	public validate( value: StringValue, property: EmailProperty ): ValidationResult {
		return new ValidationResult( [] ); // TODO
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): EmailProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as EmailProperty;
	}

	public createFormField( value: StringValue | undefined, property: EmailProperty ): any {
		return createStringFormField( value, property, 'email' );
	}

	public formatValueAsHtml( value: StringValue, property: EmailProperty ): string {
		return value.strings.join( ', ' ); // TODO
	}

}
