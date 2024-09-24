/* eslint-disable @typescript-eslint/no-explicit-any */
import { Subject } from '@neo/domain/Subject.ts';
import { SubjectId } from '@neo/domain/SubjectId.ts';
import { StatementList } from '@neo/domain/StatementList.ts';
import { createPropertyDefinitionFromJson } from '@neo/domain/PropertyDefinition.ts';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers.ts';
import { Schema } from '@neo/domain/Schema.ts';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';
import CitySchema from '../../../DemoData/Schema/City.json';
import ColumnSchema from '../../../DemoData/Schema/Column.json';
import CompanySchema from '../../../DemoData/Schema/Company.json';
import EmployeeSchema from '../../../DemoData/Schema/Employee.json';
import EverythingSchema from '../../../DemoData/Schema/Everything.json';
import PopulationSchema from '../../../DemoData/Schema/Population.json';
import ProductSchema from '../../../DemoData/Schema/Product.json';
import TableSchema from '../../../DemoData/Schema/Table.json';
import ACMEIncSubject from '../../../DemoData/Subject/ACME_Inc.json';
import BerlinSubject from '../../../DemoData/Subject/Berlin.json';
import FCaptureActualsSubject from '../../../DemoData/Subject/F_capture_actuals.json';
import NeoWikiSubject from '../../../DemoData/Subject/NeoWiki.json';
import ProfessionalWikiSubject from '../../../DemoData/Subject/Professional_Wiki.json';
import ProWikiSubject from '../../../DemoData/Subject/ProWiki.json';

export function createExampleSchemas(): Map<string, Schema> {
	const schemaDefinitions: [string, any][] = [
		[ 'City', CitySchema ],
		[ 'Column', ColumnSchema ],
		[ 'Company', CompanySchema ],
		[ 'Employee', EmployeeSchema ],
		[ 'Everything', EverythingSchema ],
		[ 'Population', PopulationSchema ],
		[ 'Product', ProductSchema ],
		[ 'Table', TableSchema ]
	];

	return new Map( schemaDefinitions.map( ( [ name, schema ] ) => [
		name,
		new Schema(
			name,
			schema.description ?? '',
			new PropertyDefinitionList(
				Object.entries( schema.propertyDefinitions ).map(
					( [ id, json ] ) => createPropertyDefinitionFromJson( id, json )
				)
			)
		)
	] ) );
}

export function createExampleSubjects(): Map<string, Subject> {
	const subjectDefinitions: any[] = [
		ACMEIncSubject,
		BerlinSubject,
		FCaptureActualsSubject,
		NeoWikiSubject,
		ProfessionalWikiSubject,
		ProWikiSubject
	];

	return new Map( subjectDefinitions.map( ( subject, index ) => {
		const mainSubject = subject.subjects[ subject.mainSubject ];
		return [
			subject.mainSubject,
			new Subject(
				new SubjectId( subject.mainSubject ),
				mainSubject.label,
				mainSubject.schema,
				StatementList.fromJsonValues( mainSubject.statements, createExampleSchemas().get( mainSubject.schema )! ),
				new PageIdentifiers( index, mainSubject.label + ' Page' )
			)
		];
	} ) );
}

console.log( 'Schemas', createExampleSchemas() );
console.log( 'Subjects', createExampleSubjects() );
