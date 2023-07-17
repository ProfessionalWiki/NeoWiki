import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type BooleanValue, ValueType } from '@/editor/domain/Value';
import type { ValueFormatInterface } from '@/editor/domain/ValueFormat';
import { ValidationResult } from '@/editor/domain/ValueFormat';

export interface CheckboxProperty extends PropertyDefinition {
}

export class CheckboxFormat implements ValueFormatInterface<CheckboxProperty, BooleanValue> {

	public readonly valueType = ValueType.Boolean;
	public readonly name = 'checkbox';

	public validate( value: BooleanValue, property: CheckboxProperty ): ValidationResult {
		return new ValidationResult( [] );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): CheckboxProperty {
		return {
			...base
		} as CheckboxProperty;
	}

	public createFormField( value: BooleanValue | undefined, property: CheckboxProperty ): any {
		return new OO.ui.CheckboxInputWidget( {
			selected: value?.boolean ?? false,
			required: property.required // TODO: verify that making the field required does not force checking the box
		} );
	}

	public formatValueAsHtml( value: BooleanValue, property: CheckboxProperty ): string {
		return value.boolean ? '<span style="color: green">✓</span>' : '<span style="color: red">✗</span>';
	}

}
