<template>
	<EditorPanes
		class="ext-neowiki-schema-editor"
		:start-pane="{ ...PROPERTY_LIST_SIZE, resizeLabel: $i18n( 'neowiki-schema-editor-resize-property-list' ).text() }"
		:stacks-below-desktop="true"
		@keydown.capture="onKeydown"
	>
		<template #start>
			<PropertyList
				:properties="currentSchema.getPropertyDefinitions()"
				:selection="selection"
				:shows-label-row="!subjectsAreDependent"
				:status-of="statusOf"
				@select="moveSelectionTo"
				@add-property="addProperty"
				@property-deleted="onPropertyDeleted"
				@property-reordered="onPropertyReordered"
			/>
		</template>

		<div
			ref="detail"
			class="ext-neowiki-schema-editor__detail"
		>
			<!-- Named after the row, for assistive technology: the creator labels its name field "Name" alone. -->
			<div
				v-if="selection.kind === 'schema'"
				role="group"
				:aria-label="$i18n( 'neowiki-schema-editor-schema' ).text()"
			>
				<slot name="name" />
				<LabelsInput
					ref="schemaLabelsInput"
					:labels="currentSchema.getLabels()"
					:label="$i18n( 'neowiki-schema-editor-labels' ).text()"
					:description="$i18n( 'neowiki-schema-editor-labels-description' ).text()"
					@update:labels="onSchemaLabelsUpdated"
				/>
				<slot name="schema" />
			</div>
			<PropertyDefinitionEditor
				v-else-if="selectedProperty !== undefined"
				ref="propertyDefinitionEditor"
				:key="propertyEditorKey"
				:property="selectedProperty as PropertyDefinition"
				:other-property-names="otherPropertyNames"
				:name-locked="selectedNameLocked"
				:select-name="selectedPropertyName === createdPropertyName"
				@update:property-definition="onPropertyUpdated"
			/>
			<template v-else>
				<h3 class="ext-neowiki-schema-editor__subject-label-heading">
					{{ $i18n( 'neowiki-schema-editor-subject-label' ).text() }}
				</h3>
				<p class="ext-neowiki-schema-editor__subject-label-help">
					{{ $i18n( 'neowiki-schema-editor-subject-label-help' ).text() }}
				</p>
			</template>
		</div>
	</EditorPanes>
</template>

<script setup lang="ts">
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import { Schema } from '@/domain/Schema.ts';
import { ComponentPublicInstance, computed, nextTick, provide, ref, watch } from 'vue';
import PropertyList from '@/components/SchemaEditor/PropertyList.vue';
import { LABEL_ROW, propertyRow, SCHEMA_ROW, type PropertyListRow } from '@/components/SchemaEditor/PropertyListRow.ts';
import { errorStatus, type ItemStatus } from '@/components/common/ItemStatus.ts';
import PropertyDefinitionEditor, { type PropertyDefinitionEditorExposes } from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import LabelsInput, { type LabelsInputExposes } from '@/components/SchemaEditor/LabelsInput.vue';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import EditorPanes, { type StartPaneOptions } from '@/components/common/EditorPanes.vue';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';
import { missingRelationAttribute } from '@/components/SchemaEditor/Property/missingRelationAttribute.ts';
import { invalidSelectOption } from '@/components/SchemaEditor/Property/selectOptionProblems.ts';
import { ErrorsRevealedKey } from '@/components/SchemaEditor/ErrorsRevealed.ts';
import { propertyNameProblemBeside, propertyNameProblemsInSchema } from '@/domain/propertyNameRules.ts';
import { propertyNameProblemMessage } from '@/components/SchemaEditor/propertyNameProblemMessage.ts';
import { focusFirstValueControl } from '@/components/common/valueControlFocus.ts';

const props = withDefaults( defineProps<{
	initialSchema: Schema;
	/**
	 * Set by a host that decides it, such as the Schema creator. Null keeps
	 * what the Schema declares.
	 */
	subjectsAreDependent?: boolean | null;
	/** Opens on the Schema's own row, for a host whose fields there come first. */
	opensOnSchema?: boolean;
	/**
	 * Whether Subjects may hold values under the names of the properties the Schema opens with. Renaming one of those
	 * properties would hide its values, so their names cannot change.
	 */
	subjectsMayUseSchema: boolean;
	/** A problem the host's fields show, marked on the Schema's row: they are out of sight while another row is selected. */
	schemaError?: string | null;
}>(), {
	subjectsAreDependent: null,
	schemaError: null
} );

const emit = defineEmits<{
	change: [];
}>();

// The host's fields of the Schema as a whole, shown while the Schema's own row is selected: creating and editing a
// Schema offer different ones. Its name field goes in `name`, ahead of the Schema's labels.
defineSlots<{
	name?(): unknown;
	schema(): unknown;
}>();

// One preference across all five dialogs this editor appears in: same two columns, and
// every one of those dialogs is the same width.
const PROPERTY_LIST_SIZE: Omit<StartPaneOptions, 'resizeLabel'> = {
	defaultSize: 320,
	minSize: 192,
	// What one of the property editor's fields needs, plus its scrollbar. Its bounds row
	// wraps rather than overflowing, so the pane no longer has to hold the whole row.
	minOtherSize: 336,
	storageKey: 'neowiki-schema-editor-pane-size'
};

const currentSchema = ref<Schema>( props.initialSchema );

// A property added under one of these names is a new property, whose name can change.
const deletedPropertyNames = ref<string[]>( [] );

const subjectsAreDependent = computed( (): boolean =>
	props.subjectsAreDependent ?? currentSchema.value.subjectsAreDependent() );

const selection = ref<PropertyListRow>( openingRow() );

const selectedPropertyName = computed( (): string | undefined => propertyNameOf( selection.value ) );

function isSelected( row: PropertyListRow ): boolean {
	return row.kind === selection.value.kind && propertyNameOf( row ) === selectedPropertyName.value;
}

function propertyNameOf( row: PropertyListRow ): string | undefined {
	return row.kind === 'property' ? row.name : undefined;
}

// Gives each selected property an editor of its own. Not the name: renaming the property
// would then rebuild the editor on every keystroke, moving the caret to the end of the
// name and dropping what the editor's fields hold.
const propertyEditorKey = ref( 0 );

// The properties the user has selected and moved away from, by name, which a deletion frees and a rename takes along.
// From then on their fields show their problems, and their rows mark them.
const touchedPropertyNames = ref( new Set<string>() );

function select( row: PropertyListRow ): void {
	if ( !isSelected( row ) ) {
		selection.value = row;
		propertyEditorKey.value++;
	}
}

function openingRow(): PropertyListRow {
	return props.opensOnSchema ? SCHEMA_ROW : defaultRow();
}

// The first property's row, else the Label row, else the Schema's own, which is always there. Dependent Subjects
// have no label.
function defaultRow(): PropertyListRow {
	const firstProperty = [ ...currentSchema.value.getPropertyDefinitions() ][ 0 ];

	if ( firstProperty !== undefined ) {
		return propertyRow( firstProperty.name.toString() );
	}

	return subjectsAreDependent.value ? SCHEMA_ROW : LABEL_ROW;
}

// The property added last, whose generated name the editor selects for replacement.
const createdPropertyName = ref<string | undefined>();

watch( () => props.initialSchema, ( schema ) => {
	currentSchema.value = schema;
	deletedPropertyNames.value = [];
	select( openingRow() );
	touchedPropertyNames.value.clear();
} );

const detail = ref<HTMLElement | null>( null );

// The first of the Schema's fields takes the focus once the Schema's row is chosen, as a property's name does when its
// editor mounts: a tick after the change renders, after the list has focused the row. Not on opening, which is no
// change: the host decides where the focus goes then.
watch( selection, async ( row ) => {
	if ( row.kind !== 'schema' ) {
		return;
	}

	await nextTick();
	focusSchemaFields();
} );

function focusSchemaFields(): void {
	if ( detail.value !== null ) {
		focusFirstValueControl( detail.value );
	}
}

const propertyDefinitionEditor = ref<( ComponentPublicInstance & PropertyDefinitionEditorExposes ) | null>( null );
const schemaLabelsInput = ref<LabelsInputExposes | null>( null );

const selectedProperty = computed( () => {
	if ( selectedPropertyName.value === undefined ) {
		return undefined;
	}

	return currentSchema.value.getPropertyDefinitions().get(
		new PropertyName( selectedPropertyName.value )
	);
} );

function propertyNamesOf( schema: Schema ): string[] {
	return Object.keys( schema.getPropertyDefinitions().asRecord() );
}

// The same array while the names stay the same, so that their problems are not worked out again on each keystroke in
// a property's other fields.
const propertyNames = computed( ( previous?: string[] ): string[] => {
	const names = propertyNamesOf( currentSchema.value as Schema );

	return previous !== undefined && sameNames( previous, names ) ? previous : names;
} );

function sameNames( names: readonly string[], otherNames: readonly string[] ): boolean {
	return names.length === otherNames.length && names.every( ( name, index ) => name === otherNames[ index ] );
}

const otherPropertyNames = computed( (): string[] =>
	propertyNames.value.filter( ( name ) => name !== selectedPropertyName.value )
);

// The properties the Schema opens with, while it holds them. Not a name the wiki refuses: the Schema cannot be saved
// until that name changes.
const lockedPropertyNames = computed( (): string[] => {
	if ( !props.subjectsMayUseSchema ) {
		return [];
	}

	const storedNames = propertyNamesOf( props.initialSchema );
	const refusedNames = new Set( propertyNameProblemsInSchema( storedNames ).map( ( problem ) => problem.name ) );

	return storedNames.filter( ( name ) => !refusedNames.has( name ) && !deletedPropertyNames.value.includes( name ) );
} );

// No other property can take a locked name while the one holding it is there, so the name identifies the property.
const selectedNameLocked = computed( (): boolean =>
	selectedPropertyName.value !== undefined && lockedPropertyNames.value.includes( selectedPropertyName.value ) );

function moveSelectionTo( row: PropertyListRow ): void {
	if ( !isSelected( row ) ) {
		leaveSelectedRow( () => select( row ) );
	}
}

// Leaving the selected row rebuilds the fields in its pane, which would drop text they cannot
// read while the user can still see it: the user is told to deal with that text first.
function leaveSelectedRow( leave: () => void ): void {
	const blocker = unparseableInput();

	if ( blocker !== null ) {
		mw.notify( blocker.message, { title: blocker.propertyName, type: 'error' } );
		return;
	}

	if ( selectedPropertyName.value !== undefined ) {
		touchedPropertyNames.value.add( selectedPropertyName.value );
	}

	leave();
}

function addProperty(): void {
	leaveSelectedRow( () => {
		const newProperty = createNewProperty();
		currentSchema.value = currentSchema.value.withAddedPropertyDefinition( newProperty );
		createdPropertyName.value = newProperty.name.toString();
		select( propertyRow( newProperty.name.toString() ) );
		emit( 'change' );
	} );
}

function createNewProperty(): PropertyDefinition {
	return {
		name: generateUniquePropertyName(),
		type: 'text',
		description: '',
		required: false,
		default: undefined
	} as PropertyDefinition;
}

function generateUniquePropertyName(): PropertyName {
	let counter = 1;

	while ( propertyNameProblemBeside( `New Property ${ counter }`, propertyNames.value ) !== null ) {
		counter++;
	}

	return new PropertyName( `New Property ${ counter }` );
}

// Caught on its way down, as Codex menus and chip inputs stop Enter, and acted on once the focused
// control is done with it, so a chip still being typed is committed rather than dropped.
function onKeydown( event: KeyboardEvent ): void {
	if ( isAddPropertyShortcut( event ) ) {
		event.preventDefault();
		setTimeout( addProperty );
	}
}

// Not the Enter that confirms an IME composition, nor the autorepeat of a held key.
function isAddPropertyShortcut( event: KeyboardEvent ): boolean {
	return event.key === 'Enter' && ( event.ctrlKey || event.metaKey ) && !event.isComposing && !event.repeat;
}

function onPropertyDeleted( name: PropertyName ): void {
	currentSchema.value = currentSchema.value.withRemovedPropertyDefinition( name );
	deletedPropertyNames.value = [ ...deletedPropertyNames.value, name.toString() ];

	if ( selectedPropertyName.value === name.toString() ) {
		select( defaultRow() );
	}

	touchedPropertyNames.value.delete( name.toString() );
	emit( 'change' );
}

function onSchemaLabelsUpdated( labels: Record<string, string> | undefined ): void {
	currentSchema.value = currentSchema.value.withLabels( labels );
	emit( 'change' );
}

function onPropertyReordered( names: PropertyName[] ): void {
	currentSchema.value = currentSchema.value.withReorderedPropertyDefinitions( names );
	emit( 'change' );
}

function onPropertyUpdated( updatedProperty: PropertyDefinition ): void {
	currentSchema.value = buildUpdatedSchema( updatedProperty );

	if ( selectedPropertyName.value !== undefined && touchedPropertyNames.value.has( selectedPropertyName.value ) ) {
		touchedPropertyNames.value.delete( selectedPropertyName.value );
		touchedPropertyNames.value.add( updatedProperty.name.toString() );
	}

	// Renamed in place: the editor showing it stays.
	selection.value = propertyRow( updatedProperty.name.toString() );
	emit( 'change' );
}

function propertyExists( name: string | undefined ): boolean {
	return name !== undefined &&
		currentSchema.value.getPropertyDefinitions().has( new PropertyName( name ) );
}

function buildUpdatedSchema( updatedProperty: PropertyDefinition ): Schema {
	if ( !propertyExists( selectedPropertyName.value ) ) {
		return currentSchema.value.withAddedPropertyDefinition( updatedProperty );
	}

	return new Schema(
		currentSchema.value.getName(),
		currentSchema.value.getDescription(),
		replacePropertyDefinition( updatedProperty ),
		currentSchema.value.subjectsAreDependent(),
		currentSchema.value.subjectsAreControlled(),
		currentSchema.value.getLabels()
	);
}

function replacePropertyDefinition( updatedProperty: PropertyDefinition ): PropertyDefinitionList {
	return new PropertyDefinitionList(
		Array.from( currentSchema.value.getPropertyDefinitions() ).map(
			function( property: PropertyDefinition ) {
				return property.name.toString() === selectedPropertyName.value ? updatedProperty : property;
			}
		)
	);
}

export interface SchemaEditorExposes {
	getSchema: () => Schema;
	saveBlocker: () => SaveBlocker | null;
	revealErrors: () => void;
	/**
	 * Selects the Schema's own row, where it is not, and focuses its first field: for a host to bring its fields
	 * into view, as when it refuses a save over one of them.
	 */
	showSchema: () => void;
}

/**
 * The first reason the save must not proceed, or null. Unparseable text comes first: the
 * user is looking at the field holding it.
 */
const saveBlocker = (): SaveBlocker | null => unparseableInput() ?? invalidPropertyName() ?? incompleteProperty();

/**
 * The field showing text that getSchema() leaves out, in the Schema's labels or the selected
 * property. Only the selected row's fields are mounted.
 */
const unparseableInput = (): SaveBlocker | null => {
	const message = ( schemaLabelsInput.value ?? propertyDefinitionEditor.value )?.unparseableInputMessage() ?? null;

	return message === null ? null : { propertyName: selectedPropertyName.value, message };
};

/**
 * The message for each property whose name the wiki would refuse to store, in the Schema's order. Read from the
 * Schema: it can open with such a name, and only the selected property has an editor mounted.
 */
const propertyNameProblems = computed( (): ReadonlyMap<string, string> => new Map(
	propertyNameProblemsInSchema( propertyNames.value ).map( ( problem ) => [ problem.name, propertyNameProblemMessage( problem ) ] )
) );

/** The first property whose name the wiki would refuse to store, or null. */
const invalidPropertyName = (): SaveBlocker | null => {
	const [ problem ] = propertyNameProblems.value;

	return problem === undefined ? null : { propertyName: problem[ 0 ], message: problem[ 1 ] };
};

/**
 * The first property definition the wiki would refuse to store, or null. Read from the Schema
 * rather than from the mounted editor: only the selected property has one, and a property the user
 * navigated away from is just as unsaveable. Only relation and select
 * properties can be faulty today; letting every Property Type answer for its own definition is
 * https://github.com/ProfessionalWiki/NeoWiki/issues/1454.
 */
const incompleteProperty = (): SaveBlocker | null => {
	for ( const property of currentSchema.value.getPropertyDefinitions() ) {
		const message = definitionProblem( property );

		if ( message !== null ) {
			return { propertyName: property.name.toString(), message };
		}
	}

	return null;
};

function definitionProblem( property: PropertyDefinition ): string | null {
	const message = missingRelationAttribute( property ) ?? invalidSelectOption( property );

	return message === null ? null : mw.message( message ).text();
}

/**
 * The mark on each row of the list. A property's problem is marked once its fields would show it: after the user
 * has left the property, or once the host has revealed the errors.
 */
function statusOf( row: PropertyListRow ): ItemStatus | null {
	if ( row.kind === 'schema' ) {
		return props.schemaError ? errorStatus( props.schemaError ) : null;
	}

	if ( row.kind === 'subjectLabel' || !errorsRevealedFor( row.name ) ) {
		return null;
	}

	const problem = propertyProblem( row.name );
	return problem === null ? null : errorStatus( problem );
}

function propertyProblem( name: string ): string | null {
	const nameProblem = propertyNameProblems.value.get( name );

	if ( nameProblem !== undefined ) {
		return nameProblem;
	}

	const properties = currentSchema.value.getPropertyDefinitions();
	const propertyName = new PropertyName( name );

	return properties.has( propertyName ) ? definitionProblem( properties.get( propertyName ) ) : null;
}

const errorsRevealed = ref( false );

function errorsRevealedFor( propertyName: string ): boolean {
	return errorsRevealed.value || touchedPropertyNames.value.has( propertyName );
}

// The fields of a property the user left show its problems on coming back, as its row does: its editor is rebuilt
// for the selection, with fields that have forgotten being left.
provide( ErrorsRevealedKey, computed( (): boolean =>
	selectedPropertyName.value !== undefined && errorsRevealedFor( selectedPropertyName.value ) ) );

/** For the host to call when it refuses a save. Selects the row the save is refused over, so its errors show. */
const revealErrors = (): void => {
	errorsRevealed.value = true;

	const blocker = saveBlocker();

	if ( blocker !== null ) {
		moveSelectionTo( blocker.propertyName === undefined ? SCHEMA_ROW : propertyRow( blocker.propertyName ) );
	}
};

defineExpose<SchemaEditorExposes>( {
	getSchema: (): Schema => currentSchema.value as Schema,
	saveBlocker,
	revealErrors,
	showSchema: (): void => isSelected( SCHEMA_ROW ) ? focusSchemaFields() : moveSelectionTo( SCHEMA_ROW )
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-editor {
	.ext-neowiki-schema-editor {
		&__detail {
			padding: @spacing-100;

			@media ( min-width: @min-width-breakpoint-desktop ) {
				padding: @spacing-50 @spacing-150 @spacing-100;
			}
		}

		/* A row reaches the dialog's edge, so the space before its icon is the dialog's own gutter, and its hover and
			selected backgrounds start at that edge. The rows have that gutter of their own; the Add row is given it. */
		&__property-list {
			@media ( min-width: @min-width-breakpoint-desktop ) {
				padding-block: 0 @spacing-50;

				.ext-neowiki-property-list__add-item {
					padding-inline-start: @spacing-150;
					border-start-start-radius: 0;
					border-end-start-radius: 0;
				}

				/* As far from the column divider's line, in the middle of its track, as from the dialog's edge. */
				.ext-neowiki-property-list__item--schema::after {
					inset-inline: @spacing-150 calc( @spacing-150 - @spacing-75 / 2 );
				}
			}
		}

		&__subject-label-heading {
			margin: 0;
			padding-block: 0;
			font-size: @font-size-medium;
		}

		&__subject-label-help {
			margin: @spacing-50 0 0;
		}
	}

	.cdx-select-vue {
		display: block; /* Make the select element take the full width of the parent element */
	}

	/*
		TODO: Temporary solution for responsive layout.
		Property list and editor should be in multiple steps for mobile.
	*/
	@media ( max-width: @max-width-breakpoint-tablet ) {
		.ext-neowiki-schema-editor {
			&__property-list {
				overflow-x: auto;
				padding: 0;
				display: flex;
			}

			&__property-list .ext-neowiki-property-list {
				display: flex;
				white-space: nowrap;

				/* Side by side, the rows have no gutter: a mark sits on each row's icon. */
				.ext-neowiki-property-list__item {
					padding-inline-start: @spacing-75;
					border-radius: 0;
				}

				/* The strip scrolls, and so would cut a handle's menu short; rows still drag, and Alt+arrows move them. */
				.ext-neowiki-reorder-handle {
					display: none;
				}

				.ext-neowiki-property-list__item--schema {
					margin-block-end: 0;

					&::after {
						display: none;
					}
				}

				.ext-neowiki-property-list__add-item {
					margin-block-start: 0;
				}
			}

			&__detail {
				border-block-start: @border-subtle;
			}
		}
	}
}
</style>
