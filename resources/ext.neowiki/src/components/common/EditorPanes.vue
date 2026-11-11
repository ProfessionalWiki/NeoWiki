<template>
	<div
		ref="root"
		class="ext-neowiki-editor-panes"
		:class="{
			'ext-neowiki-editor-panes--two': showsStartPane(),
			'ext-neowiki-editor-panes--stacks-below-desktop': stacksBelowDesktop
		}"
		:style="showsStartPane() ? { '--ext-neowiki-pane-size': paneSize?.cssSize.value } : undefined"
	>
		<!-- A placeholder while hidden, so the main pane keeps its place and is never remounted:
			a host may hold unsaved values in it. -->
		<template v-if="showsStartPane()">
			<div
				:id="startPaneId"
				class="ext-neowiki-editor-panes__pane"
			>
				<slot name="start" />
			</div>

			<PaneDivider
				class="ext-neowiki-editor-panes__divider"
				:label="startPane?.resizeLabel ?? ''"
				:controls="startPaneId"
				:size="paneSize?.size.value ?? 0"
				:min="paneSize?.minSize.value ?? 0"
				:max="paneSize?.maxSize.value ?? 0"
				:disabled="paneSize?.resizable.value !== true"
				@resize="paneSize?.resizeTo( $event )"
				@commit="paneSize?.persist()"
			/>
		</template>

		<div
			ref="mainPane"
			class="ext-neowiki-editor-panes__pane"
		>
			<slot />
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, useSlots } from 'vue';
import { useGeneratedId } from '@wikimedia/codex';
import PaneDivider, { PANE_DIVIDER_SIZE } from '@/components/common/PaneDivider.vue';
import { usePaneSize, type PaneSizeOptions } from '@/composables/usePaneSize.ts';

export interface StartPaneOptions extends Omit<PaneSizeOptions, 'dividerSize'> {
	/** Names the divider that resizes the start pane, for assistive technology. */
	resizeLabel: string;
}

/**
 * The body of an editor: one pane, or a start pane beside it, such as a list of what the main
 * pane edits, sized with a divider. Each pane scrolls on its own and fades at an edge its content
 * runs past. Their content carries its own insets.
 */
const props = defineProps<{
	/** For an editor with a start pane: read once, when the editor is set up. */
	startPane?: StartPaneOptions;
	/** Below desktop width, puts the start pane above the main one and leaves the scrolling to the dialog. */
	stacksBelowDesktop?: boolean;
}>();

const slots = useSlots();

const root = ref<HTMLElement | null>( null );
const mainPane = ref<HTMLElement | null>( null );

// Generated rather than fixed: two editors can be on the page at once, one dialog over another.
const startPaneId = useGeneratedId( 'ext-neowiki-start-pane' );

const paneSize = props.startPane === undefined ?
	null :
	usePaneSize( root, { ...props.startPane, dividerSize: PANE_DIVIDER_SIZE } );

// Asked on every render rather than computed: the slots a host passes are not reactive, and a host
// can add or drop the start pane between renders.
function showsStartPane(): boolean {
	return paneSize !== null && slots.start !== undefined;
}

defineExpose( { mainPane } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

/* `minmax( 0, 1fr )` for the row: each pane stretches to it and scrolls itself, which keeps the
	start pane in view while the main one is scrolled. `min-height: 0` is what lets this grid, and
	the panes in it, shrink below their content in a dialog of bounded height.

	jsdom resolves no layout, so the suite cannot see this grid collapse. Measure every change in a
	browser, at more than one viewport height. */
.ext-neowiki-editor-panes {
	display: grid;
	grid-template-columns: minmax( 0, 1fr );
	grid-template-rows: minmax( 0, 1fr );
	min-height: 0;

	/* The reader's width, bounded in script rather than here: the observed width it is divided
		against is a border box, which `100%` in a track is not. `minmax( 0, 1fr )` rather than
		`auto`, or the main pane's min-content would claim space back out of a width the reader set.
		No inline padding or border here, for the same reason. */
	&--two {
		grid-template-columns: var( --ext-neowiki-pane-size ) @spacing-75 minmax( 0, 1fr );
	}

	/* Scrolling shows as a fade at the edges the content runs past, not as lines under the header
		and above the footer, which Codex draws only for a dialog body that scrolls itself. */
	&__pane {
		.ext-neowiki-scroll-fade();
		min-width: 0;
		min-height: 0;
		overflow-y: auto;
		overscroll-behavior: none;
		/* Held whether or not it scrolls, so content long enough to scroll is laid out as wide as
			content that is not. */
		scrollbar-gutter: stable;
	}

	/* The divider is hidden only where the panes are stacked, a single track: hiding a grid item
		inside an explicit three-track list would leave its track behind and land the main pane in
		it. */
	&--stacks-below-desktop {
		@media ( max-width: @max-width-breakpoint-tablet ) {
			grid-template-columns: minmax( 0, 1fr );
			grid-template-rows: none;
			min-height: auto;

			.ext-neowiki-editor-panes__divider {
				display: none;
			}

			.ext-neowiki-editor-panes__pane {
				overflow-y: visible;
				scrollbar-gutter: auto;

				&::before,
				&::after {
					display: none;
				}
			}
		}
	}
}
</style>
