import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { Relation, RelationValue, ValueType } from '@/editor/domain/Value';
import { RelationMultiselectWidgetFactory } from '@/editor/presentation/Widgets/RelationMultiselectWidgetFactory';
import {
	isRelationLookupWidget,
	RelationLookupWidgetFactory
} from '@/editor/presentation/Widgets/RelationLookupWidgetFactory';
import { BaseValueFormat, ValidationResult } from '@/editor/domain/ValueFormat';
import type { RelationTargetSuggester } from '@/editor/application/RelationTargetSuggester';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import type { ProgressBarWidget } from '@/editor/presentation/Widgets/ProgressBarWidgetFactory';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { SubjectId } from '@/editor/domain/SubjectId';
import type { PageUrlBuilder } from '@/editor/infrastructure/PageUrlBuilder';
import type { SubjectMap } from '@/editor/domain/SubjectMap';
import type { NeoWikiExtension } from '@/NeoWikiExtension';
import type { CellData } from '@/editor/presentation/SubjectTableLoader';

export interface RelationProperty extends PropertyDefinition {

	readonly relation: string;
	readonly targetSchema: string;
	readonly multiple?: boolean;
	readonly uniqueItems?: boolean;

}

export class RelationFormat extends BaseValueFormat<RelationProperty, RelationValue, ProgressBarWidget|OO.ui.TagMultiselectWidget> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'relation';
	private readonly factory: RelationServicesFactory;

	public constructor( factory: NeoWikiExtension ) {
		super();
		this.factory = new RelationServicesFactory( factory );
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

	public async getFieldData( field: ProgressBarWidget|OO.ui.TagMultiselectWidget ): Promise<FieldData> {
		if ( isRelationLookupWidget( field ) ) {
			return field.getFieldData();
		}

		const isValid: boolean = ( field as OO.ui.TagMultiselectWidget ).checkValidity();
		const fieldValue = ( field as OO.ui.TagMultiselectWidget ).getValue() as string[];
		const value = new RelationValue( fieldValue.map( ( targetId ) => new Relation( undefined, targetId ) ) );

		return {
			value: value,
			valid: isValid,
			errorMessage: undefined
		};
	}

	public createTableEditorColumn( property: RelationProperty ): ColumnDefinition {
		const column: ColumnDefinition = super.createTableEditorColumn( property );
		return this.factory.getColumnBuilder().createTableEditorColumn( column, property );
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

	public getColumnBuilder(): RelationColumnBuilder {
		return new RelationColumnBuilder( this.factory.getPageUrlBuilder() );
	}

}

class RelationColumnBuilder {

	public constructor(
		private readonly pageUrlBuilder: PageUrlBuilder
	) {
	}

	public createTableEditorColumn( column: ColumnDefinition, _: RelationProperty ): ColumnDefinition {
		column.formatter = this.relationsFormatter.bind( this );

		return column;
	}

	private relationsFormatter( cell: CellComponent ): string {
		const relationValue = cell.getValue() as RelationValue;

		if ( relationValue === undefined || relationValue.relations === undefined ) {
			return '';
		}

		const referencedSubjects = ( cell.getData() as CellData ).referencedSubjects;

		return relationValue.relations
			.map( ( relation ) => this.formatRelation( relation, referencedSubjects ) )
			.join( ', ' );
	}

	private formatRelation( relation: Relation, referencedSubjects: SubjectMap ): string {
		const subject = referencedSubjects.get( new SubjectId( relation.target ) );

		if ( subject === undefined ) {
			return '';
		}

		const url = this.pageUrlBuilder.buildUrl( subject.getPageIdentifiers().getPageName() );
		return `<a href="${url}">${subject.getLabel()}</a>`;
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
