import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { Relation, RelationValue, ValueType } from '@/editor/domain/Value';
import { type RelationMultiselectWidget, RelationMultiselectWidgetFactory } from '@/editor/presentation/Widgets/RelationMultiselectWidgetFactory';
import {
	type RelationLookupWidget,
	RelationLookupWidgetFactory
} from '@/editor/presentation/Widgets/RelationLookupWidgetFactory';
import { BaseValueFormat, type ValidationError, ValidationResult } from '@/editor/domain/ValueFormat';
import type { RelationTargetSuggester } from '@/editor/application/RelationTargetSuggester';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import type { PageUrlBuilder } from '@/editor/infrastructure/PageUrlBuilder';
import type { NeoWikiExtension } from '@/NeoWikiExtension';
import type { CellData } from '@/editor/presentation/SubjectTableLoader';
import type { VueComponentManager } from '@/editor/presentation/Vue/VueComponentManager';
import VueRelation from '@/components/propertyValues/Relation.vue';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';
import { Uuid } from '@/editor/infrastructure/Uuid';

export interface RelationProperty extends PropertyDefinition {

	readonly relation: string;
	readonly targetSchema: string;
	readonly multiple?: boolean;
	readonly uniqueItems?: boolean; // TODO: remove

}

export interface RelationAttributes extends PropertyAttributes {
	readonly relation?: string;
	readonly targetSchema?: string;
	readonly multiple?: boolean;
	readonly uniqueItems?: boolean;
}

export class RelationFormat extends BaseValueFormat<RelationProperty, RelationValue, RelationLookupWidget|RelationMultiselectWidget, RelationAttributes> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'relation';
	private readonly factory: RelationServicesFactory;

	public constructor( factory: NeoWikiExtension, private readonly vueComponentManager: VueComponentManager ) {
		super();
		this.factory = new RelationServicesFactory( factory );
	}

	public getExampleValue( property: RelationProperty ): RelationValue {
		const relations = [ new Relation( undefined, Uuid.getRandomUUID() ) ];
		if ( property !== undefined && property.multiple ) {
			relations.push( new Relation( undefined, Uuid.getRandomUUID() ) );
		}

		return new RelationValue( relations );
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

	public createFormField( value: RelationValue | undefined, property: RelationProperty ): OO.ui.Widget {
		value ||= new RelationValue( [] );

		if ( property.multiple ) {
			const widget = RelationMultiselectWidgetFactory.create( {
				targetSchema: property.targetSchema,
				relationTargetSuggester: this.factory.getRelationTargetSuggester()
				// TODO: allow duplicates when property.uniqueItems is false
				// TODO: how to handle required?
			} );

			widget.setDefaultValues( value ).then();
			return widget;
		}

		return RelationLookupWidgetFactory.create( {
			selected: value,
			targetSchema: property.targetSchema,
			relationTargetSuggester: this.factory.getRelationTargetSuggester(),
			required: property.required
		} );
	}

	public getFieldData( field: RelationLookupWidget|RelationMultiselectWidget ): RelationValue {
		return field.getFieldData();
	}

	public createTableEditorColumn( property: RelationProperty ): ColumnDefinition {
		const column: ColumnDefinition = super.createTableEditorColumn( property );

		column.formatter = ( cell: CellComponent ) => {
			return this.vueComponentManager.createDivWithComponent( VueRelation, {
				property: property,
				value: new RelationValue( cell.getValue() ?? [] ),
				referencedSubjects: ( cell.getData() as CellData ).referencedSubjects,
				pageUrlBuilder: this.factory.getPageUrlBuilder()
			} );
		};

		return column;
	}

	public getAttributes( base: PropertyAttributes ): RelationAttributes {
		return {
			...base,
			relation: '',
			targetSchema: '',
			multiple: false,
			uniqueItems: false
		};
	}

	public getFieldElement( field: RelationLookupWidget|RelationMultiselectWidget, property: RelationProperty ): HTMLInputElement {
		if ( property.multiple ) {
			const multipleField = field as RelationMultiselectWidget;
			return multipleField.input.$input[ 0 ] as HTMLInputElement;
		}
		return ( field as RelationLookupWidget ).$input[ 0 ] as HTMLInputElement;
	}

}

class RelationServicesFactory {

	public constructor(
		private readonly factory: NeoWikiExtension
	) {
	}

	public getRelationTargetSuggester(): RelationTargetSuggester {
		return this.factory.getRelationTargetSuggester();
	}

	public getPageUrlBuilder(): PageUrlBuilder {
		return this.factory.getPageUrlBuilder();
	}
}

export function newRelationProperty( name: string, targetSchema?: string, multiple?: boolean ): RelationProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.Relation,
		format: RelationFormat.formatName,
		description: '',
		required: false,
		default: '',
		relation: 'MyRelation',
		targetSchema: targetSchema ?? 'MyTargetSchema',
		multiple: multiple ?? false
	};
}
