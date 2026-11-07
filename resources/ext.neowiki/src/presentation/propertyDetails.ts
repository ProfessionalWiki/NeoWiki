import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { Severity } from '@/domain/Severity';
import { newNumberValue, newStringValue } from '@/domain/Value';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText';
import { type RelationProperty, RelationType } from '@/domain/propertyTypes/Relation';
import type { SelectOption } from '@/domain/propertyTypes/Select';
import { schemaReferenceText } from '@/domain/SchemaReference';
import { valueText } from '@/presentation/valueText';

interface ConstraintLine {
	readonly text: string;
	readonly severity: Severity | null;
}

// Each Property Type that has one of these Constraints gives it the same name (see docs/api/schema-format.md), so
// they are read by name rather than type by type.
type ConstraintFields = Partial<{
	readonly multiple: boolean;
	readonly uniqueItems: boolean;
	readonly uniqueLanguages: boolean;
	readonly minimum: number | string;
	readonly maximum: number | string;
	readonly minLength: number;
	readonly maxLength: number;
	readonly options: readonly SelectOption[];
}>;

/**
 * What the property takes beyond its type, as one line: a relation's target Schemas, its Constraints other than
 * Required, and its initial value. Null when there is nothing to add.
 */
export function typeDetails( property: PropertyDefinition, validationEnforced: boolean ): string | null {
	const details = [
		...targetsDetail( property ),
		...constraintLines( property ).map( ( line ) => constraintDetail( line, validationEnforced ) ),
		...initialValueDetail( property ),
	];

	return details.length > 0 ? details.join( ' · ' ) : null;
}

function targetsDetail( property: PropertyDefinition ): string[] {
	if ( property.type !== RelationType.typeName ) {
		return [];
	}

	const targets = ( property as RelationProperty ).targetSchemas.map( schemaReferenceText );

	return [ mw.message( 'neowiki-schema-display-detail-targets', targets.join( ', ' ) ).text() ];
}

/**
 * Taking one value is the default and gets no line. Taking multiple values gets one, which constrains nothing and so
 * has no severity.
 */
function constraintLines( property: PropertyDefinition ): ConstraintLine[] {
	const fields = property as PropertyDefinition & ConstraintFields;

	return [
		...valueCountLines( property, fields ),
		fields.minimum !== undefined ? constraintLine(
			property,
			'minimum',
			mw.message( 'neowiki-schema-display-constraint-minimum', boundText( property, fields.minimum ) ).text(),
		) : null,
		fields.maximum !== undefined ? constraintLine(
			property,
			'maximum',
			mw.message( 'neowiki-schema-display-constraint-maximum', boundText( property, fields.maximum ) ).text(),
		) : null,
		fields.minLength !== undefined ? constraintLine(
			property,
			'minLength',
			mw.message( 'neowiki-schema-display-constraint-min-length', fields.minLength ).text(),
		) : null,
		fields.maxLength !== undefined ? constraintLine(
			property,
			'maxLength',
			mw.message( 'neowiki-schema-display-constraint-max-length', fields.maxLength ).text(),
		) : null,
		fields.options !== undefined && fields.options.length > 0 ? constraintLine(
			property,
			'options',
			mw.message( 'neowiki-schema-display-constraint-options', fields.options.map( ( option ) => option.label ).join( ', ' ) ).text(),
		) : null,
	].filter( ( line ): line is ConstraintLine => line !== null );
}

function valueCountLines( property: PropertyDefinition, fields: ConstraintFields ): ConstraintLine[] {
	if ( fields.multiple !== true ) {
		return [];
	}

	if ( property.type === MonolingualTextType.typeName ) {
		return [ fields.uniqueLanguages === true ?
			constraintLine( property, 'uniqueLanguages', mw.message( 'neowiki-schema-display-constraint-one-per-language' ).text() ) :
			lineWithoutSeverity( mw.message( 'neowiki-schema-display-constraint-multiple-per-language' ).text() ) ];
	}

	return [
		lineWithoutSeverity( mw.message( 'neowiki-schema-display-constraint-multiple' ).text() ),
		...( fields.uniqueItems === true ?
			[ constraintLine( property, 'uniqueItems', mw.message( 'neowiki-schema-display-constraint-unique-items' ).text() ) ] :
			[] ),
	];
}

function constraintLine( property: PropertyDefinition, constraint: string, text: string ): ConstraintLine {
	return { text: text, severity: severityOf( property, constraint ) };
}

function severityOf( property: PropertyDefinition, constraint: string ): Severity {
	return property.constraintSeverities?.[ constraint ] ?? 'warning';
}

function lineWithoutSeverity( text: string ): ConstraintLine {
	return { text: text, severity: null };
}

function boundText( property: PropertyDefinition, bound: number | string ): string {
	const value = typeof bound === 'number' ? newNumberValue( bound ) : newStringValue( bound );

	return valueText( property, value ) ?? String( bound );
}

function constraintDetail( line: ConstraintLine, validationEnforced: boolean ): string {
	if ( line.severity !== 'error' ) {
		return line.text;
	}

	return mw.message(
		validationEnforced ? 'neowiki-schema-display-detail-blocks-saving' : 'neowiki-schema-display-detail-error',
		line.text,
	).text();
}

function initialValueDetail( property: PropertyDefinition ): string[] {
	const text = property.default !== undefined ? valueText( property, property.default ) : null;

	return text !== null ? [ mw.message( 'neowiki-schema-display-detail-initial-value', text ).text() ] : [];
}

/**
 * What happens to a Subject saved without a value for the property, or null when the property does not require one.
 */
export function requiredExplanation( property: PropertyDefinition, validationEnforced: boolean ): string | null {
	if ( !property.required ) {
		return null;
	}

	if ( severityOf( property, 'required' ) === 'warning' ) {
		return mw.message( 'neowiki-schema-display-required-warning' ).text();
	}

	return mw.message(
		validationEnforced ? 'neowiki-schema-display-required-blocks-saving' : 'neowiki-schema-display-required-error',
	).text();
}
