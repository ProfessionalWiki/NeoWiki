<template>
	<div class="ext-neowiki-target-schemas-input">
		<CdxMultiselectLookup
			v-if="schemasLoaded"
			v-model:input-value="inputText"
			:input-chips="chips"
			:selected="localTargets"
			:menu-items="menuItems"
			:placeholder="$i18n( 'neowiki-schema-picker-placeholder' ).text()"
			:aria-label="$i18n( 'neowiki-property-editor-target-schema' ).text()"
			@update:selected="setLocalTargets"
			@update:input-chips="keepTargetsOfChips"
			@input="onInput"
		/>
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
import { computed, ref } from 'vue';
import { CdxInfoChip, CdxMultiselectLookup } from '@wikimedia/codex';
import type { ChipInputItem, MenuItemData } from '@wikimedia/codex';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { isLocalSchemaReference, schemaReferenceText, type SchemaReference } from '@/domain/SchemaReference';
import { schemaMenuItems } from '@/presentation/schemaMenuItems.ts';

const props = defineProps<{
	modelValue: readonly SchemaReference[];
}>();

const emit = defineEmits<{
	'update:modelValue': [ targetSchemas: SchemaReference[] ];
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

const menuItems = computed( (): MenuItemData[] => schemaMenuItems( summaries.value, query.value ) );

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

function onInput( value: string | number ): void {
	query.value = String( value );
}

function sameNames( names: readonly string[], otherNames: readonly string[] ): boolean {
	return names.length === otherNames.length && names.every( ( name, index ) => name === otherNames[ index ] );
}

function setLocalTargets( names: readonly string[] ): void {
	if ( sameNames( names, localTargets.value ) ) {
		return;
	}

	// Codex clears the search only for a chip it adds itself, and these chips follow the targets.
	if ( names.some( ( name ) => !localTargets.value.includes( name ) ) ) {
		inputText.value = '';
		query.value = '';
	}

	emit( 'update:modelValue', [ ...names, ...otherSourceTargets.value ] );
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
