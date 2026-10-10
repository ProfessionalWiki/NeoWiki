<template>
	<NeoPopover
		class="ext-neowiki-split-button"
		:open="props.open"
		placement="top-end"
		:offset="8"
		@update:open="emit( 'update:open', $event )"
	>
		<template #trigger="{ setTrigger, toggle, open: isOpen, panelId }">
			<CdxButton
				class="ext-neowiki-split-button__action"
				action="progressive"
				weight="primary"
				:disabled="props.disabled || props.actionDisabled"
				@click="emit( 'click' )"
			>
				<CdxIcon
					v-if="props.icon"
					:icon="props.icon"
				/>
				<span class="ext-neowiki-split-button__label">{{ props.label }}</span>
			</CdxButton>

			<CdxButton
				:ref="setTrigger"
				class="ext-neowiki-split-button__toggle"
				action="progressive"
				weight="primary"
				:disabled="props.disabled"
				aria-haspopup="true"
				:aria-expanded="isOpen"
				:aria-controls="panelId"
				:aria-label="props.toggleLabel"
				@click="toggle"
				@keydown="onToggleKeydown"
			>
				<CdxIcon
					:icon="cdxIconExpand"
					size="x-small"
				/>
			</CdxButton>
		</template>

		<!-- Keyed so the host gets fresh content on each opening: a search half typed and then
			dismissed is not an answer to come back to. -->
		<div :key="openEpoch">
			<slot />
		</div>
	</NeoPopover>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconExpand } from '@wikimedia/codex-icons';
import type { Icon } from '@wikimedia/codex-icons';
import NeoPopover from '@/components/common/NeoPopover.vue';

const props = withDefaults(
	defineProps<{
		/** What the action half does, stated in full: "Create on this page", not "Create". */
		label: string;
		/** Names the toggle for anyone who cannot see which button it sits against. */
		toggleLabel: string;
		open: boolean;
		icon?: Icon;
		/** Stops both halves: nothing here can be done at all, as while a save is out. */
		disabled?: boolean;
		/**
		 * Stops the action half alone. What the popover holds is often what makes the action
		 * possible, so switching it off along with the action would shut the only way out.
		 */
		actionDisabled?: boolean;
	}>(),
	{
		icon: undefined,
		disabled: false,
		actionDisabled: false
	}
);

const emit = defineEmits<{
	click: [];
	'update:open': [ open: boolean ];
}>();

// Counted rather than toggled, so each opening is a new value and the content inside is rebuilt.
const openEpoch = ref( 0 );

watch( () => props.open, ( open ) => {
	if ( open ) {
		openEpoch.value++;
	}
} );

// What a menu trigger does, and all this one adds to the click the popover already handles.
function onToggleKeydown( event: KeyboardEvent ): void {
	if ( event.key === 'ArrowDown' && !props.open ) {
		event.preventDefault();
		emit( 'update:open', true );
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/* The divider between the two halves, drawn the way Codex draws the one between the buttons of a
	toggle button group. A shadow is painted outside the border box, so unlike anything laid inside
	the button it escapes the `overflow: hidden` Codex puts on `.cdx-button` — which is what
	swallowed this divider while it was a pseudo-element. */
@divider-shadow: -@border-width-base 0 0 0 @box-shadow-color-inverted;

/* Codex's focus ring for a primary button, restated so the divider can be kept alongside it. */
@focus-ring-progressive: inset 0 0 0 @border-width-base @box-shadow-color-progressive--focus, inset 0 0 0 ( @border-width-base * 2 ) @box-shadow-color-inverted;

.ext-neowiki-split-button {
	display: flex;
	/* A primary action fills the row it is given, as the plain button it replaces did. The label
		half takes what the chevron does not. */
	width: @size-full;
	min-width: 0;

	&__action.cdx-button {
		flex: 1 1 auto;
		justify-content: center;
		border-start-end-radius: 0;
		border-end-end-radius: 0;
		min-width: 0;
		/* Room above and below a label that wraps; one line still sits inside the minimum height. */
		padding-block: @spacing-25;
	}

	&__toggle.cdx-button {
		position: relative;
		box-shadow: @divider-shadow;
		border-start-start-radius: 0;
		border-end-start-radius: 0;
		padding-inline: @spacing-35;
		flex-shrink: 0;
	}

	/* Focus replaces a button's whole box-shadow, divider included, so the divider is restated
		with each ring. The divider is written first because CSSJanus negates the x-offset of the
		first shadow in a list and no other: behind a ring's own `inset 0` it would keep pointing
		left in a right-to-left interface, landing on the outer edge instead of between the halves.
		Both selectors carry the toggle's class twice to outrank keyboard-focus.less, whose own
		primary-progressive rule is nine classes deep and is what these two answer. */
	&__toggle&__toggle.cdx-button:enabled.cdx-button--weight-primary.cdx-button--action-progressive:focus-visible:not( :active ):not( .cdx-button--is-active ) {
		box-shadow: @divider-shadow, @focus-ring-progressive;
	}

	/* A mouse click focuses the button without earning a ring, and NeoWiki takes Codex's away
		again - taking the divider with it, since it lives in the same property. Put back. */
	&__toggle&__toggle.cdx-button:enabled.cdx-button--weight-primary.cdx-button--action-progressive:focus:not( :focus-visible ):not( :active ):not( .cdx-button--is-active ) {
		box-shadow: @divider-shadow;
	}

	/* Each half is its own tab stop, so each needs its own ring — and the ring has to sit above
		the neighbour that would otherwise paint over it. */
	&__action.cdx-button:focus-visible,
	&__toggle.cdx-button:focus-visible {
		position: relative;
		z-index: 1;
	}

	/* The destination is what the label is for, so a long one wraps rather than losing its tail to
		an ellipsis - which at phone width is where the page name sits. */
	&__label {
		text-align: start;
		/* Codex keeps a button's text on one line. */
		white-space: normal;
		overflow-wrap: anywhere;
	}
}
</style>
