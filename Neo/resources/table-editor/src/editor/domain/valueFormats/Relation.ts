import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { Relation, RelationValue, ValueType } from '@/editor/domain/Value';
import {
	type RelationMultiselectWidget,
	RelationMultiselectWidgetFactory
} from '@/editor/presentation/Widgets/RelationMultiselectWidgetFactory';
import {
	type RelationLookupWidget,
	RelationLookupWidgetFactory
} from '@/editor/presentation/Widgets/RelationLookupWidgetFactory';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';
import type { RelationTargetSuggester } from '@/editor/application/RelationTargetSuggester';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import type { PageUrlBuilder } from '@/editor/infrastructure/PageUrlBuilder';
import type { NeoWikiExtension } from '@/NeoWikiExtension';
import type { CellData } from '@/editor/presentation/SubjectTableLoader';
import type { VueComponentManager } from '@/editor/presentation/Vue/VueComponentManager';
import VueRelation from '@/components/propertyValues/Relation.vue';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

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

	public getExampleValue(): RelationValue {
		// TODO: verify a relation without id can be rendered as example
		return new RelationValue( [ new Relation( undefined, 'Some relation' ) ] );
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

	public async getFieldData( field: RelationLookupWidget|RelationMultiselectWidget ): Promise<FieldData> {
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

export function newRelationProperty( name: string ): RelationProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.Relation,
		format: RelationFormat.formatName,
		description: '',
		required: false,
		default: '',
		relation: 'MyRelation',
		targetSchema: 'MyTargetSchema'
	};
}
