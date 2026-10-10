<template>
	<div class="ext-neowiki-data-export">
		<a
			class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled"
			:href="props.jsonUrl"
			target="_blank"
			rel="noopener"
		>
			<CdxIcon :icon="cdxIconDownload" />
			{{ props.jsonLabel }}
		</a>
		<div
			ref="rdfRef"
			class="ext-neowiki-data-export__rdf"
			@focusout="onFocusOut"
		>
			<CdxButton
				ref="triggerRef"
				aria-haspopup="listbox"
				:aria-expanded="expanded"
				:aria-controls="menuId"
				@click="onTriggerClick"
				@keydown="onTriggerKeydown"
			>
				<CdxIcon :icon="cdxIconDownload" />
				{{ props.rdfLabel }}
			</CdxButton>
			<div class="ext-neowiki-data-export__menu">
				<CdxMenu
					:id="menuId"
					ref="menuRef"
					v-model:expanded="expanded"
					:selected="null"
					:menu-items="menuItems"
					:visible-item-limit="10"
					@update:selected="onSelect"
				/>
			</div>
		</div>
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
import type { MenuItemValue } from '@wikimedia/codex';
import { cdxIconDownload } from '@wikimedia/codex-icons';
import { rdfMenuItems } from '@/presentation/DataExportMenu.ts';
import type { ExportUrls } from '@/presentation/DataExportMenu.ts';

const props = defineProps<ExportUrls & {
	jsonLabel: string;
	rdfLabel: string;
	projections: readonly string[];
}>();

const expanded = ref( false );

const rdfRef = ref<HTMLElement | null>( null );
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
		// Opening from the button's end keeps a menu wider than the button inside the row, whose end
		// the button sits at.
		placement: 'bottom-end',
		offset: 4
	}
);

const menuItems = computed( () => rdfMenuItems( props.projections, props.rdfUrl ) );

function onTriggerClick( event: MouseEvent ): void {
	// Enter/Space on a native button also dispatch a click, but with detail 0; those are handled
	// in onTriggerKeydown, so only genuine pointer clicks (detail > 0) toggle the menu here.
	// Without this guard, keyboard activation would both open (here) and act (keydown).
	if ( event.detail === 0 ) {
		return;
	}
	expanded.value = !expanded.value;
}

function onSelect( url: MenuItemValue | null ): void {
	if ( url === null ) {
		return;
	}
	window.open( String( url ), '_blank', 'noopener' );
}

function onTriggerKeydown( event: KeyboardEvent ): void {
	if ( event.key === 'Escape' ) {
		if ( expanded.value ) {
			event.preventDefault();
			expanded.value = false;
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
		expanded.value = true;
	} else if ( [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ].includes( event.key ) ) {
		event.preventDefault();
		expanded.value = true;
		nextTick()
			.then( () => menuRef.value?.delegateKeyNavigation( event ) )
			.catch( ( error ) => {
				console.error( 'Failed to hand the opening key to the export menu:', error );
			} );
	}
}

function onFocusOut( event: FocusEvent ): void {
	const nextTarget = event.relatedTarget as Node | null;
	if ( nextTarget !== null && rdfRef.value?.contains( nextTarget ) ) {
		return;
	}
	expanded.value = false;
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-data-export {
	display: inline-flex;
	gap: @spacing-50;

	&__menu {
		position: relative;

		// Codex sizes the menu to the button, which is narrower than an item naming a projection.
		.cdx-menu {
			min-width: max-content;
		}
	}
}
</style>
