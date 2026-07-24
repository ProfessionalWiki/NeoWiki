<template>
	<div
		ref="rootRef"
		class="ext-neowiki-data-export"
		@focusout="onFocusOut"
	>
		<CdxButton
			ref="triggerRef"
			class="ext-neowiki-data-export__trigger"
			aria-haspopup="listbox"
			:aria-expanded="expanded"
			:aria-controls="menuId"
			@click="onTriggerClick"
			@keydown="onTriggerKeydown"
		>
			<CdxIcon :icon="cdxIconDownload" />
			{{ props.label }}
		</CdxButton>
		<div class="ext-neowiki-data-export__menu">
			<CdxMenu
				:id="menuId"
				ref="menuRef"
				:selected="selected"
				:expanded="expanded"
				:menu-items="menuItems"
				:visible-item-limit="10"
				@update:selected="onSelect"
				@update:expanded="onMenuExpandedChange"
			>
				<template #default="{ menuItem }">
					<span class="ext-neowiki-data-export__item">
						<span class="ext-neowiki-data-export__item-start">
							<CdxIcon
								v-if="menuItem.icon"
								class="ext-neowiki-data-export__item-icon"
								:icon="menuItem.icon"
								size="small"
							/>
							<span
								class="ext-neowiki-data-export__item-label"
								:title="menuItem.label"
							>{{ menuItem.label }}</span>
						</span>
						<CdxIcon
							v-if="opensProjectionList( menuItem.value )"
							class="ext-neowiki-data-export__item-chevron"
							:icon="cdxIconNext"
							size="small"
						/>
					</span>
				</template>
			</CdxMenu>
		</div>
		<span
			class="ext-neowiki-data-export__sr-only"
			aria-live="polite"
			aria-atomic="true"
		>{{ stepMessage }}</span>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import {
	CdxButton,
	CdxIcon,
	CdxMenu,
	useFloatingMenu,
	useGeneratedId
} from '@wikimedia/codex';
import type { MenuItemData, MenuItemValue } from '@wikimedia/codex';
import { cdxIconDownload, cdxIconNext, cdxIconPrevious } from '@wikimedia/codex-icons';
import { projectionLabel } from '@/presentation/DataExportMenu.ts';

type RdfFormat = 'turtle' | 'trig';

const props = defineProps<{
	label: string;
	jsonUrl: string;
	rdfUrl( projection: string, format: RdfFormat ): string;
	projections: readonly string[];
}>();

// Sentinel value for the "back to the format list" item; kept distinct from any real projection
// name (projection names come from ontology mappings and never look like this).
const BACK = '__back__';

const expanded = ref( false );
// The menu is a one-shot action list rather than a persistent selection, so `selected` is reset
// to null right after every pick (see onSelect) -- it never reflects a lasting choice.
const selected = ref<MenuItemValue | null>( null );
const step = ref<'format' | 'projection'>( 'format' );
const chosenFormat = ref<RdfFormat | null>( null );

// Announced to assistive tech when the menu swaps between the format and projection levels in
// place -- the listbox gives no cue on its own that its contents changed under the same control.
const stepMessage = ref( '' );

const rootRef = ref<HTMLElement | null>( null );
const triggerRef = ref<InstanceType<typeof CdxButton>>();
const menuRef = ref<InstanceType<typeof CdxMenu>>();
const menuId = useGeneratedId( 'ext-neowiki-data-export-menu' );

// CdxButton narrows its own `$emit` type to its declared `emits: ['click']`, which TypeScript's
// structural checking then treats as incompatible with the generic `ComponentPublicInstance`
// shape `useFloatingMenu` expects for the reference element. The cast documents that mismatch as
// deliberate: at runtime this is just the button's root DOM element, which is all FloatingUI needs.
useFloatingMenu(
	triggerRef as unknown as Parameters<typeof useFloatingMenu>[0],
	menuRef,
	{
		// Size the menu to the trigger width (Codex's default), not the available width --
		// `useAvailableWidth` would stretch it toward the viewport edge, which makes the footer
		// buttons' menus span the whole row rather than staying a bounded dropdown.
		placement: 'bottom-start',
		offset: 4
	}
);

const formatItems = computed<MenuItemData[]>( () => [
	{ value: 'json', label: mw.msg( 'neowiki-managesubjects-export-json' ) },
	{ value: 'turtle', label: mw.msg( 'neowiki-managesubjects-export-format-turtle' ) },
	{ value: 'trig', label: mw.msg( 'neowiki-managesubjects-export-format-trig' ) }
] );

// Turtle/TriG open the projection sub-list rather than downloading immediately; a trailing chevron
// (rendered via the menu item slot) marks that second step, mirroring the leading back-chevron on
// the projection level.
const NESTED_FORMATS: readonly MenuItemValue[] = [ 'turtle', 'trig' ];

function opensProjectionList( value: MenuItemValue ): boolean {
	return NESTED_FORMATS.includes( value );
}

const projectionItems = computed<MenuItemData[]>( () => [
	{
		value: BACK,
		label: mw.msg( 'neowiki-managesubjects-export-back' ),
		icon: cdxIconPrevious
	},
	...props.projections.map( ( projection ): MenuItemData => ( {
		value: projection,
		label: projectionLabel( projection )
	} ) )
] );

const menuItems = computed<MenuItemData[]>(
	() => step.value === 'format' ? formatItems.value : projectionItems.value
);

function openInNewTab( url: string ): void {
	window.open( url, '_blank', 'noopener' );
}

function close(): void {
	expanded.value = false;
	step.value = 'format';
	chosenFormat.value = null;
	selected.value = null;
	stepMessage.value = '';
}

function open(): void {
	step.value = 'format';
	expanded.value = true;
}

function onTriggerClick( event: MouseEvent ): void {
	// Enter/Space on a native button also dispatch a click, but with detail 0; those are handled
	// in onTriggerKeydown, so only genuine pointer clicks (detail > 0) toggle the menu here.
	// Without this guard, keyboard activation would both open (here) and act (keydown) -- the flash.
	if ( event.detail === 0 ) {
		return;
	}
	if ( expanded.value ) {
		close();
	} else {
		open();
	}
}

function onSelect( value: MenuItemValue | null ): void {
	// Clear the highlight so the same item can be selected again later.
	selected.value = null;
	if ( value === null ) {
		return;
	}

	if ( step.value === 'format' ) {
		if ( value === 'json' ) {
			openInNewTab( props.jsonUrl );
			close();
		} else {
			// Turtle/TriG: reveal the projection list rather than navigating. Deliberately not
			// relying on CdxMenu's own `update:expanded` here -- a real (non-stubbed) CdxMenu
			// always emits `update:expanded(false)` right after any single-select item pick, which
			// would immediately collapse the menu again and defeat this second step.
			chosenFormat.value = value as RdfFormat;
			step.value = 'projection';
			stepMessage.value = mw.msg( 'neowiki-managesubjects-export-choose-projection' );
		}
		return;
	}

	if ( value === BACK ) {
		step.value = 'format';
		chosenFormat.value = null;
		stepMessage.value = mw.msg( 'neowiki-managesubjects-export-choose-format' );
		return;
	}

	if ( chosenFormat.value !== null ) {
		openInNewTab( props.rdfUrl( String( value ), chosenFormat.value ) );
		close();
	}
}

function onMenuExpandedChange( isExpanded: boolean ): void {
	// Only honour the child opening itself (e.g. an arrow key while the trigger has focus).
	// Closing is entirely driven by our own step/selection logic above, plus Escape and
	// focus-out below -- seeing that logic here too would race with it (see onSelect).
	if ( isExpanded ) {
		expanded.value = true;
	}
}

function onTriggerKeydown( event: KeyboardEvent ): void {
	if ( event.key === 'Escape' ) {
		if ( expanded.value ) {
			event.preventDefault();
			close();
		}
		return;
	}

	if ( expanded.value ) {
		// Once open, the menu owns navigation and selection. It preventDefaults the keys it
		// handles, so the trigger's synthesised click never double-fires.
		menuRef.value?.delegateKeyNavigation( event );
		return;
	}

	// Closed: open on the standard menu activation keys. Enter/Space just open; the arrow and
	// Home/End keys open and then hand the same key to the menu so it highlights the right item.
	if ( event.key === 'Enter' || event.key === ' ' ) {
		event.preventDefault();
		open();
	} else if ( [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ].includes( event.key ) ) {
		event.preventDefault();
		open();
		nextTick()
			.then( () => menuRef.value?.delegateKeyNavigation( event ) )
			.catch( ( error ) => {
				console.error( 'Failed to hand the opening key to the export menu:', error );
			} );
	}
}

function onFocusOut( event: FocusEvent ): void {
	const nextTarget = event.relatedTarget as Node | null;
	if ( nextTarget !== null && rootRef.value?.contains( nextTarget ) ) {
		return;
	}
	if ( expanded.value ) {
		close();
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-data-export {
	position: relative;
	display: inline-block;

	&__menu {
		position: relative;
	}

	&__item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: @spacing-50;
		width: 100%;
	}

	&__item-start {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		min-width: 0;
	}

	&__item-label {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__item-icon.cdx-icon {
		flex-shrink: 0;
	}

	&__item-chevron.cdx-icon {
		flex-shrink: 0;
		color: @color-subtle;
	}

	&__sr-only {
		position: absolute;
		width: 1px;
		height: 1px;
		margin: -1px;
		padding: 0;
		overflow: hidden;
		clip-path: inset( 50% );
		white-space: nowrap;
		border: 0;
	}
}
</style>
