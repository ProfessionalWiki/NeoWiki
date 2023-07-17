import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { RelationValue, ValueType } from '@/editor/domain/Value';
import { RelationMultiselectWidgetFactory } from '@/editor/presentation/Widgets/RelationMultiselectWidgetFactory';
import { RelationLookupWidgetFactory } from '@/editor/presentation/Widgets/RelationLookupWidgetFactory';
import type { ValueFormatInterface } from '@/editor/domain/ValueFormat';
import { ValidationResult } from '@/editor/domain/ValueFormat';
import type { RelationTargetSuggester } from '@/editor/application/RelationTargetSuggester';
import { Format, PropertyName } from '@/editor/domain/PropertyDefinition';
import type { TextProperty } from '@/editor/domain/valueFormats/Text';

export interface RelationProperty extends PropertyDefinition {

	readonly relation: string;
	readonly targetSchema: string;
	readonly multiple?: boolean;
	readonly uniqueItems?: boolean;

}

export class RelationFormat implements ValueFormatInterface<RelationProperty, RelationValue> {

	public readonly valueType = ValueType.String;
	public readonly name = 'relation';

	public constructor(
		private readonly relationTargetSuggester: RelationTargetSuggester
	) {
	}

	public validate( value: RelationValue, property: RelationProperty ): ValidationResult {
		return new ValidationResult( [] );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): RelationProperty {
		return {
			...base,
			relation: json.relation,
			targetSchema: json.targetSchema,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as RelationProperty;
	}

	public createFormField( value: RelationValue | undefined, property: RelationProperty ): any {
		value ||= new RelationValue( [] );

		if ( property.multiple ) {
			const widget = RelationMultiselectWidgetFactory.create( {
				targetSchema: property.targetSchema,
				relationTargetSuggester: this.relationTargetSuggester
				// TODO: allow duplicates when property.uniqueItems is false
				// TODO: how to handle required?
			} );

			widget.setDefaultValues( value ).then();
			return widget;
		}

		return RelationLookupWidgetFactory.create( {
			selected: value,
			targetSchema: property.targetSchema,
			relationTargetSuggester: this.relationTargetSuggester,
			required: property.required
		} );
	}

	public formatValueAsHtml( value: RelationValue, property: RelationProperty ): string {
		return value.targetIds.join( ', ' ); // TODO
	}

}

export function newRelationProperty( name: string ): RelationProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.Relation,
		format: 'relation' as Format,
		description: '',
		required: false,
		default: '',
		relation: 'MyRelation',
		targetSchema: 'MyTargetSchema'
	};
}
