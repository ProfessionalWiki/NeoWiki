<template>
	<div class="ext-neowiki-target-schemas-input">
		<CdxMultiselectLookup
			v-if="schemasLoaded"
			v-model:input-value="inputText"
			:input-chips="chips"
			:selected="localTargets"
			:menu-items="menu.items"
			:menu-config="menu.config"
			:placeholder="$i18n( 'neowiki-schema-picker-placeholder' ).text()"
			:aria-label="$i18n( 'neowiki-property-editor-target-schema' ).text()"
			@update:selected="onSelect"
			@update:input-chips="keepTargetsOfChips"
			@input="onInput"
		>
			<template
				v-if="leftOutKindReason !== null"
				#no-results
			>
				{{ leftOutKindReason }}
			</template>
		</CdxMultiselectLookup>
		<!-- Shown apart from the field: Codex 1.14 turns a clicked chip back into text to search for,
			and the menu offers no Schema of another Source to pick it again. -->
		<div
			v-if="otherSourceTargets.length > 0"
			class="ext-neowiki-target-schemas-input__other-sources"
		>
			<CdxInfoChip
				v-for="reference in otherSourceTargets"
				:key="schemaReferenceText( reference )"
			>
				{{ schemaReferenceText( reference ) }}
			</CdxInfoChip>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxInfoChip, CdxMultiselectLookup } from '@wikimedia/codex';
import type { ChipInputItem } from '@wikimedia/codex';
import { normalizeSchemaName, useSchemaStore } from '@/stores/SchemaStore.ts';
import { type SchemaSummary, subjectsAreDependentIn } from '@/application/SchemaLookup.ts';
import { isLocalSchemaReference, schemaReferenceText, type SchemaReference } from '@/domain/SchemaReference';
import { CREATE_SCHEMA, schemaMenu, type SchemaMenu } from '@/presentation/schemaMenu.ts';
import { targetsDependentSubjects } from '@/domain/propertyTypes/Relation.ts';

const props = defineProps<{
	modelValue: readonly SchemaReference[];
	/**
	 * Ends the list with an offer to create a Schema named after the typed text, unless a Schema or a
	 * target has that name already. Choosing the offer emits `create` with the text, and with whether
	 * the targets' Subjects are dependent, which the new Schema must match, or null where unknown.
	 */
	offersCreation?: boolean;
}>();

const emit = defineEmits<{
	'update:modelValue': [ targetSchemas: SchemaReference[] ];
	'create': [ schemaName: string, subjectsAreDependent: boolean | null ];
}>();

const schemaStore = useSchemaStore();
const summaries = ref<SchemaSummary[]>( [] );
const schemasLoaded = ref( false );
const inputText = ref<string | number>( '' );
const query = ref( '' );

const localTargets = computed( (): string[] => props.modelValue.filter( isLocalSchemaReference ) );
const otherSourceTargets = computed( (): SchemaReference[] =>
	props.modelValue.filter( ( reference ) => !isLocalSchemaReference( reference ) )
);

function chipFor( schemaName: string ): ChipInputItem {
	return { value: schemaName, label: schemaName };
}

const chips = computed( (): ChipInputItem[] => localTargets.value.map( chipFor ) );

const typedName = computed( (): string => String( inputText.value ).trim() );

// Schema names are unique, so a name that is taken can only be picked. A target's name is taken even
// while the list leaves it out.
const takenNames = computed( (): Set<string> => new Set(
	[ ...summaries.value.map( ( summary ) => summary.name ), ...localTargets.value ].map( normalizeSchemaName )
) );

const creationOffered = computed( (): boolean =>
	props.offersCreation && !takenNames.value.has( normalizeSchemaName( typedName.value ) ) );

// The targets of one property all have Dependent Subjects or none does, so once one is chosen, only
// Schemas like it are offered.
const propertyTargetsDependentSubjects = computed( (): boolean | null =>
	targetsDependentSubjects( localTargets.value, subjectsAreDependentIn( summaries.value ) ) );

const offeredSummaries = computed( (): SchemaSummary[] => propertyTargetsDependentSubjects.value === null ?
	summaries.value :
	summaries.value.filter( ( summary ) => summary.subjectsAreDependent === propertyTargetsDependentSubjects.value ) );

const menu = computed( (): SchemaMenu =>
	schemaMenu( offeredSummaries.value, query.value, creationOffered.value ? typedName.value : null ) );

// Where the search finds Schemas only of the kind the targets rule out, a menu with nothing to offer says why.
const leftOutKindReason = computed( (): string | null => {
	const search = query.value.trim().toLowerCase();
	const targetsDependent = propertyTargetsDependentSubjects.value;

	if ( targetsDependent === null || search === '' || !summaries.value.some( ( summary ) =>
		summary.subjectsAreDependent !== targetsDependent && summary.name.toLowerCase().includes( search ) ) ) {
		return null;
	}

	return targetsDependent ?
		mw.msg( 'neowiki-property-editor-target-schema-dependent-only' ) :
		mw.msg( 'neowiki-property-editor-target-schema-standalone-only' );
} );

// CdxMultiselectLookup opens its menu on focus only when it has items by then, so the field is
// rendered once the Schemas have arrived, or a failure to load them has been reported.
async function loadSchemas(): Promise<void> {
	try {
		summaries.value = await schemaStore.fetchAllSchemaSummaries();
	} catch ( error ) {
		console.error( 'Failed to load schemas for the target schemas field:', error );
	}

	schemasLoaded.value = true;
}

loadSchemas();

// A target the list leaves out, such as a Schema created from the offer, may be newer than the list.
watch( localTargets, ( names, previousNames ) => {
	const listedNames = summaries.value.map( ( summary ) => summary.name );

	if ( names.some( ( name ) => !previousNames.includes( name ) && !listedNames.includes( name ) ) ) {
		loadSchemas();
	}
} );

function onInput( value: string | number ): void {
	query.value = String( value );
}

function sameNames( names: readonly string[], otherNames: readonly string[] ): boolean {
	return names.length === otherNames.length && names.every( ( name, index ) => name === otherNames[ index ] );
}

// Codex adds the value of a chosen menu item to the selection, the offer's too, which is no target.
function onSelect( names: readonly string[] ): void {
	if ( names.includes( CREATE_SCHEMA ) ) {
		const schemaName = typedName.value;
		// Cleared as a pick clears it: the Schema the offer creates comes back as a target.
		clearSearch();
		emit( 'create', schemaName, propertyTargetsDependentSubjects.value );
		return;
	}

	setLocalTargets( names );
}

function setLocalTargets( names: readonly string[] ): void {
	if ( sameNames( names, localTargets.value ) ) {
		return;
	}

	// Codex clears the search only for a chip it adds itself, and these chips follow the targets.
	if ( names.some( ( name ) => !localTargets.value.includes( name ) ) ) {
		clearSearch();
	}

	emit( 'update:modelValue', [ ...names, ...otherSourceTargets.value ] );
}

function clearSearch(): void {
	inputText.value = '';
	query.value = '';
}

// Only a pick from the menu adds a target. A chip the field removes takes its target with it; one it adds
// of its own accord, as Codex 1.14 does with the text typed when a chip is clicked, picks nothing.
function keepTargetsOfChips( items: ChipInputItem[] ): void {
	const chipValues = new Set( items.map( ( chip ) => chip.value ) );
	setLocalTargets( localTargets.value.filter( ( name ) => chipValues.has( name ) ) );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/* Holds the height of the field while it waits for the Schemas, so the editor does not jump when it
	appears. */
.ext-neowiki-target-schemas-input {
	min-height: @min-size-interactive-pointer;

	&__other-sources {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-25;
		margin-top: @spacing-50;
	}
}
</style>
