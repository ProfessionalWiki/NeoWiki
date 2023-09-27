import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type BooleanValue, newBooleanValue, ValueType } from '@/editor/domain/Value';
import { BaseValueFormat } from '@/editor/domain/ValueFormat';
import { ValidationResult } from '@/editor/domain/ValueFormat';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

export interface CheckboxProperty extends PropertyDefinition {
}

interface CheckboxAttributes extends PropertyAttributes {
}

export class CheckboxFormat extends BaseValueFormat<CheckboxProperty, BooleanValue, OO.ui.CheckboxInputWidget, CheckboxAttributes> {

	public static readonly valueType = ValueType.Boolean;
	public static readonly formatName = 'checkbox';

	public getExampleValue(): boolean {
		return true;
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

	public async getFieldData( field: OO.ui.CheckboxInputWidget, property: CheckboxProperty ): Promise<FieldData> {
		return {
			value: newBooleanValue( field.isSelected() ),
			valid: true,
			errorMessage: undefined
		};
	}

	public getAttributes( base: PropertyAttributes ): CheckboxAttributes {
		return { ...base };
	}
}
