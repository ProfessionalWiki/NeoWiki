import type { Schema } from '@/domain/Schema';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { type PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, ValueType } from '@/domain/Value';
import { resolveSelectLabel, type SelectProperty, SelectType } from '@/domain/propertyTypes/Select';
import { TextType } from '@/domain/propertyTypes/Text';

const PLACEHOLDER = /\{([^{}]*)\}/g;

/**
 * The property a placeholder's text names, spaces around it aside, as the backend reads it; null for
 * empty braces, which name none.
 */
function propertyNameIn( placeholderText: string ): PropertyName | null {
	return PropertyName.isValid( placeholderText ) ? new PropertyName( placeholderText ) : null;
}

/**
 * A Schema's label template with the placeholders naming one property renamed to another, and the rest
 * of it left as written. A placeholder names the property its braces hold, spaces around it aside, as
 * the backend reads it. A name with braces fits in no placeholder, so it renames none: the template
 * keeps naming the old one, which saving then refuses until the name is changed back.
 */
export function withRenamedPlaceholder( template: string, from: string, to: string ): string {
	if ( from === to || /[{}]/.test( to ) ) {
		return template;
	}

	return template.replace(
		PLACEHOLDER,
		( placeholder: string, text: string ) => propertyNameIn( text )?.toString() === from ? `{${ to }}` : placeholder,
	);
}

/**
 * The property whose field names a Subject of the Schema, the one its label template's first placeholder
 * names; null for a Schema without a template.
 */
export function namingPropertyName( schema: Schema ): string | null {
	const template = schema.getLabelTemplate() ?? '';
	const name = [ ...template.matchAll( PLACEHOLDER ) ]
		.map( ( match ) => propertyNameIn( match[ 1 ] ) )
		.find( ( placeholderName ) => placeholderName !== null );

	return name?.toString() ?? null;
}

/**
 * How a new Subject of the Schema is named by the name typed for it, if any: its label, its Statements, and
 * the name it is shown under, as the server would derive it (ADR 31, ADR 35). A Schema whose label template
 * names its Subjects from a text field gets the name in that field, not as a label that would outrank the
 * template for good.
 */
export function newSubjectNaming( schema: Schema, typedName: string | null ): {
	label: string | null;
	statements: StatementList;
	displayName: string;
	displayNameIsGenerated: boolean;
} {
	const naming = typedName === null ? null : namingStatement( schema, typedName );
	const label = naming === null ? typedName : null;
	const statements = new StatementList( naming === null ? [] : [ naming ] );
	const shownName = label ?? templateLabel( schema, statements );

	return {
		label,
		statements,
		displayName: shownName ?? schema.getName(),
		displayNameIsGenerated: shownName === null,
	};
}

/**
 * The name as the value of the property the label template names first. Null when the Schema has no
 * template, or that property is not text, since a typed name is no number, option or text in a
 * language as it stands.
 */
function namingStatement( schema: Schema, name: string ): Statement | null {
	const propertyName = namingPropertyName( schema );

	if ( propertyName === null || !schema.getPropertyDefinitions().has( new PropertyName( propertyName ) ) ) {
		return null;
	}

	const property = schema.getPropertyDefinition( propertyName );

	return property.type === TextType.typeName ?
		new Statement( property.name, property.type, newStringValue( name ) ) :
		null;
}

/**
 * The label the Schema's label template gives a Subject with these Statements, or null when the Schema
 * has no template or none of its placeholders has a value. The backend's LabelTemplate and
 * LabelTemplateRenderer compute the label the Subject is shown under once saved; this mirrors them, so
 * an editor can show that label while the Statements change. Change one and change the other.
 */
export function templateLabel( schema: Schema, statements: StatementList ): string | null {
	const template = schema.getLabelTemplate();

	if ( template === null ) {
		return null;
	}

	let anyValue = false;

	const label = template.replace( PLACEHOLDER, ( placeholder: string, text: string ) => {
		const name = propertyNameIn( text );

		if ( name === null ) {
			return placeholder;
		}

		const value = textOf( name, schema, statements );
		anyValue = anyValue || value !== '';

		return value;
	} );

	const collapsed = anyValue ? trimAsciiWhitespace( label.replace( /[ \t\n\v\f\r]+/g, ' ' ) ) : '';

	return collapsed === '' ? null : collapsed;
}

function textOf( name: PropertyName, schema: Schema, statements: StatementList ): string {
	if ( !statements.has( name ) ) {
		return '';
	}

	const property = schema.getPropertyDefinitions().has( name ) ? schema.getPropertyDefinition( name ) : undefined;

	return trimAsciiWhitespace( textsOf( statements.get( name ), property )[ 0 ] ?? '' );
}

/**
 * A select value reads as its option labels; every other type as the strings, numbers or texts it stores,
 * as each core type's backend search text does.
 */
function textsOf( statement: Statement, property: PropertyDefinition | undefined ): string[] {
	const value = statement.value;

	switch ( value?.type ) {
		case ValueType.String:
			return statement.propertyType === SelectType.typeName ?
				selectLabels( value.parts, property ) :
				value.parts;
		case ValueType.Number:
			return [ String( value.number ) ];
		case ValueType.MonolingualText:
			return value.parts.map( ( part ) => part.text );
		default:
			return [];
	}
}

/**
 * Only against a definition that is still a select: the option ids mean nothing to any other type.
 */
function selectLabels( ids: string[], property: PropertyDefinition | undefined ): string[] {
	if ( property?.type !== SelectType.typeName ) {
		return [];
	}

	return ids
		.map( ( id ) => resolveSelectLabel( property as SelectProperty, id ) )
		.filter( ( label ): label is string => label !== undefined );
}

/**
 * The backend trims only ASCII whitespace, so a label keeps any other space it starts or ends with.
 */
function trimAsciiWhitespace( text: string ): string {
	return text.replace( /^[ \t\n\v\r]+|[ \t\n\v\r]+$/g, '' );
}
