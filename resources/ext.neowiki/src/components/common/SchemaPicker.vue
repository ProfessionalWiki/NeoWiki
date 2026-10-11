<template>
	<div class="ext-neowiki-schema-picker">
		<CdxLookup
			v-if="schemasLoaded"
			ref="lookupRef"
			v-model:selected="selectedSchema"
			v-model:input-value="inputText"
			:menu-items="menuItems"
			:menu-config="menuConfig"
			:placeholder="$i18n( 'neowiki-schema-picker-placeholder' ).text()"
			@input="filterSchemas"
			@update:selected="onSelect"
			@blur="revertUncommittedTyping"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { CdxLookup } from '@wikimedia/codex';
import type { MenuConfig, MenuItemData } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import { normalizeSchemaName, useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import { schemaMenuItems } from '@/presentation/schemaMenuItems.ts';

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
}>();

const emit = defineEmits<{
	'select': [ schemaName: string ];
	'create': [ schemaName: string ];
}>();

// Codex has no value of its own for the offer, so it carries a value no Schema name can take: a name
// is a page title, and a title never starts with an underscore.
const CREATE_SCHEMA = '__create__';

// CdxLookup hands its whole menuConfig to its CdxMenu, whose footer Codex's MenuConfig type leaves out.
type MenuConfigWithFooter = MenuConfig & { footer: MenuItemData | null };

const schemaStore = useSchemaStore();
const selectedSchema = ref<string | null>( props.selected ?? null );
const inputText = ref<string | number>( props.selected ?? '' );
const summaries = ref<SchemaSummary[]>( [] );
const schemasLoaded = ref( false );
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

const offeredSummaries = computed( (): SchemaSummary[] => props.schemaNames === undefined ?
	summaries.value :
	summaries.value.filter( ( summary ) => props.schemaNames?.includes( summary.name ) ) );

const matchingItems = computed<MenuItemData[]>( () => schemaMenuItems( offeredSummaries.value, query.value ) );

// Below Schemas, the offer is the menu's footer, which Codex keeps in view while the list scrolls.
// Alone, it is the list's one item: CdxLookup neither opens a menu nor moves through it by keyboard
// while it has no items.
const menuItems = computed<MenuItemData[]>( () =>
	creationOffered.value && matchingItems.value.length === 0 ? [ createItem() ] : matchingItems.value );

const menuConfig = computed( (): MenuConfigWithFooter => ( {
	footer: creationOffered.value && matchingItems.value.length > 0 ? createItem() : null
} ) );

function createItem(): MenuItemData {
	return {
		value: CREATE_SCHEMA,
		label: typedName.value === '' ?
			mw.msg( 'neowiki-schema-picker-create' ) :
			mw.msg( 'neowiki-schema-picker-create-named', typedName.value ),
		icon: cdxIconAdd
	};
}

// CdxLookup decides whether focus opens the menu from the menu items it was created
// with, so the field is only rendered once the schemas have arrived. Loading them is
// also marked done when the request fails, so a failure leaves a usable field rather
// than none at all.
async function loadSchemas(): Promise<void> {
	try {
		summaries.value = await schemaStore.fetchAllSchemaSummaries();
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
