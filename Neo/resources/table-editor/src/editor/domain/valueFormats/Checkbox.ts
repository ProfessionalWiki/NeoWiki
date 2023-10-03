import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type BooleanValue, newBooleanValue, ValueType } from '@/editor/domain/Value';
import { BaseValueFormat } from '@/editor/domain/ValueFormat';
import { ValidationResult } from '@/editor/domain/ValueFormat';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

export interface CheckboxProperty extends PropertyDefinition {
}

interface CheckboxAttributes extends PropertyAttributes {
}

export class CheckboxFormat extends BaseValueFormat<CheckboxProperty, BooleanValue, OO.ui.CheckboxInputWidget, CheckboxAttributes> {

	public static readonly valueType = ValueType.Boolean;
	public static readonly formatName = 'checkbox';

	public getExampleValue(): BooleanValue {
		return newBooleanValue( true );
	}

	public validate( value: BooleanValue, property: CheckboxProperty ): ValidationResult {
		return new ValidationResult( [] );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): CheckboxProperty {
		return {
			...base
		} as CheckboxProperty;
	}

	public createFormField( value: BooleanValue | undefined, property: CheckboxProperty ): OO.ui.CheckboxInputWidget {
		return new OO.ui.CheckboxInputWidget( {
			selected: value?.boolean ?? false,
			required: property.required // TODO: verify that making the field required does not force checking the box
		} );
	}

	public getFieldData( field: OO.ui.CheckboxInputWidget ): BooleanValue {
		return newBooleanValue( field.isSelected() );
	}

	public getAttributes( base: PropertyAttributes ): CheckboxAttributes {
		return { ...base };
	}
}
