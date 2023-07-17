import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type NumberValue, ValueType } from '@/editor/domain/Value';
import { ProgressBarWidgetFactory } from '@/editor/presentation/Widgets/ProgressBarWidgetFactory';
import type { ValueFormatInterface } from '@/editor/domain/ValueFormat';
import { ValidationResult } from '@/editor/domain/ValueFormat';

export interface ProgressProperty extends PropertyDefinition {

	readonly minimum: number;
	readonly maximum: number;
	readonly step: number;

}

export class ProgressFormat implements ValueFormatInterface<ProgressProperty, NumberValue> {

	public readonly valueType = ValueType.Number;
	public readonly name = 'progress';

	public validate( value: NumberValue, property: ProgressProperty ): ValidationResult {
		return new ValidationResult( [] ); // TODO
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): ProgressProperty {
		return {
			...base,
			minimum: json.minimum,
			maximum: json.maximum,
			step: json.step
		} as ProgressProperty;
	}

	public createFormField( value: NumberValue | undefined, property: ProgressProperty ): any {
		const progressBar = ProgressBarWidgetFactory.create( {
			progress: value?.number ?? 0,
			min: property.minimum ?? 0,
			max: property.maximum ?? 100,
			step: property.step ?? 1
		} );
		progressBar.appendLabel(); // TODO: add option to prop definition?
		return progressBar;
	}

	public formatValueAsHtml( value: NumberValue, property: ProgressProperty ): string {
		return ''; // TODO
	}

}
