<template>
	<div class="ext-neowiki-schema-picker">
		<slot
			v-if="nothingToOffer && $slots[ 'no-schemas' ]"
			name="no-schemas"
		/>
		<CdxLookup
			v-else-if="schemasLoaded"
			ref="lookupRef"
			v-model:selected="selectedSchema"
			v-model:input-value="inputText"
			:menu-items="menu.items"
			:menu-config="menu.config"
			:placeholder="$i18n( 'neowiki-schema-picker-placeholder' ).text()"
			:aria-label="props.ariaLabel"
			@input="filterSchemas"
			@update:selected="onSelect"
			@blur="revertUncommittedTyping"
		>
			<template
				v-if="leftOutDependentSchema !== null"
				#no-results
			>
				{{ $i18n( 'neowiki-create-subject-error-dependent-subjects', leftOutDependentSchema ).text() }}
			</template>
		</CdxLookup>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { CdxLookup } from '@wikimedia/codex';
import { normalizeSchemaName, useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { CREATE_SCHEMA, schemaMenu, type SchemaMenu } from '@/presentation/schemaMenu.ts';

const props = defineProps<{
	/**
	 * The committed Schema. Changing it to one the list leaves out makes the picker fetch the list
	 * again, so a Schema created since is listed.
	 */
	selected?: string | null;
	/**
	 * Ends the list with an offer to create a Schema named after the typed text, unless a Schema has
	 * that name already. Choosing the offer emits `create` with the text.
	 */
	offersCreation?: boolean;
	/** The Schemas to offer, of those the wiki has. Absent, all of them. */
	schemaNames?: readonly string[];
	/** Names the field for a caller that shows no label of its own. */
	ariaLabel?: string;
	/** Leaves out the Schemas whose Subjects are dependent. */
	standaloneOnly?: boolean;
}>();

const emit = defineEmits<{
	'select': [ schemaName: string ];
	'create': [ schemaName: string ];
}>();

defineSlots<{
	/** Shown in place of the field where the wiki has no Schemas and the picker offers to create none. */
	'no-schemas'?(): unknown;
}>();

const schemaStore = useSchemaStore();
const selectedSchema = ref<string | null>( props.selected ?? null );
const inputText = ref<string | number>( props.selected ?? '' );
const summaries = ref<SchemaSummary[]>( [] );
const schemasLoaded = ref( false );
// A failed request leaves `summaries` empty too, so only a list that loaded can tell the wiki has none.
const wikiHasNoSchemas = ref( false );
const query = ref<string>( '' );
const lookupRef = ref<InstanceType<typeof CdxLookup> | null>( null );

const typedName = computed( (): string => String( inputText.value ).trim() );

// Schema names are unique, so a name that is taken can only be picked. The committed Schema's name is
// taken even while the list leaves it out.
const takenNames = computed( (): Set<string> => {
	const names = summaries.value.map( ( summary ) => summary.name );

	return new Set( ( props.selected ? [ ...names, props.selected ] : names ).map( normalizeSchemaName ) );
} );

const creationOffered = computed( (): boolean =>
	props.offersCreation && !takenNames.value.has( normalizeSchemaName( typedName.value ) ) );

const offeredSummaries = computed( (): SchemaSummary[] => summaries.value.filter( ( summary ) =>
	( props.schemaNames === undefined || props.schemaNames.includes( summary.name ) ) &&
	!( props.standaloneOnly && summary.subjectsAreDependent ) ) );

const menu = computed( (): SchemaMenu =>
	schemaMenu( offeredSummaries.value, query.value, creationOffered.value ? typedName.value : null ) );

// A Schema the search finds that is left out for having Dependent Subjects, the one it names in full first, so that a
// menu with nothing to offer can say why.
const leftOutDependentSchema = computed( (): string | null => {
	const search = query.value.trim().toLowerCase();

	if ( !props.standaloneOnly || search === '' ) {
		return null;
	}

	const found = summaries.value.filter( ( summary ) => summary.subjectsAreDependent &&
		( props.schemaNames === undefined || props.schemaNames.includes( summary.name ) ) &&
		summary.name.toLowerCase().includes( search ) );

	return ( found.find( ( summary ) => summary.name.toLowerCase() === search ) ?? found[ 0 ] )?.name ?? null;
} );

const nothingToOffer = computed( (): boolean => wikiHasNoSchemas.value && !props.offersCreation );

// CdxLookup decides whether focus opens the menu from the menu items it was created
// with, so the field is only rendered once the schemas have arrived. Loading them is
// also marked done when the request fails, so a failure leaves a usable field rather
// than none at all.
async function loadSchemas(): Promise<void> {
	try {
		summaries.value = await schemaStore.fetchAllSchemaSummaries();
		wikiHasNoSchemas.value = summaries.value.length === 0;
	} catch ( error ) {
		console.error( 'Failed to load schemas for the picker:', error );
	}
	schemasLoaded.value = true;
}

const schemasReady = loadSchemas();

// The filter is reset alongside the field: CdxLookup resolves a selection against the
// menu as it currently stands, and would empty the field for a schema the filter hides.
// A committed schema the list leaves out, such as one created from the offer, may be newer
// than the list, so the list is fetched again. Text typed in the meantime is not overwritten.
watch( () => props.selected, async ( value ) => {
	selectedSchema.value = selectableSchema( value ?? null );
	inputText.value = value ?? '';
	query.value = '';

	if ( value && selectedSchema.value === null ) {
		await loadSchemas();

		if ( inputText.value === value ) {
			selectedSchema.value = selectableSchema( value );
		}
	}
} );

// CdxLookup takes the field's text for a selection from the matching menu entry and
// empties the field when there is none, so it is only handed a schema it lists. The
// field keeps showing the committed name either way.
function selectableSchema( schemaName: string | null ): string | null {
	return offeredSummaries.value.some( ( summary ) => summary.name === schemaName ) ? schemaName : null;
}

function filterSchemas( value: string ): void {
	query.value = value;
}

// CdxLookup reports a selection only for a menu entry the user picks, and null while
// they type, so a schema name typed out in full is not picked by itself.
//
// Setting the field text is load-bearing: left to do it itself, CdxLookup announces the
// name as typed input, which would re-apply the filter cleared on the next line.
// Restoring the full menu means reopening the picker without leaving the field browses
// every schema again rather than the last filter.
function onSelect( schemaName: string | null ): void {
	if ( schemaName === CREATE_SCHEMA ) {
		// Cleared before Codex sees it as the selection, which would replace the typed text in the field.
		selectedSchema.value = null;
		emit( 'create', typedName.value );
		return;
	}

	if ( schemaName === null ) {
		return;
	}

	inputText.value = schemaName;
	query.value = '';

	if ( schemaName !== props.selected ) {
		emit( 'select', schemaName );
	}
}

function revertUncommittedTyping(): void {
	selectedSchema.value = selectableSchema( props.selected ?? null );
	inputText.value = props.selected ?? '';
	query.value = '';
}

// Waits for the schemas so that focus lands on a field whose menu can open right away.
async function focus(): Promise<void> {
	await schemasReady;
	await nextTick();
	const input = ( lookupRef.value?.$el as HTMLElement )?.querySelector( 'input' );
	input?.focus();
}

defineExpose( { focus } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/* The field is withheld until the schemas arrive (see above), so without a reserved
	height the picker paints a frame with nothing here, and a dialog sized to its content
	resizes twice. Holding the control's height keeps the box the same before and after
	the schemas land. The token is the Codex control height, so the reservation tracks the
	field it stands in for. */
.ext-neowiki-schema-picker {
	min-height: @min-size-interactive-pointer;

	.cdx-lookup {
		width: 100%;
	}
}
</style>
