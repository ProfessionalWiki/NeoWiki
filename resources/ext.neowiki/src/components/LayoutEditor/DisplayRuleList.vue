<template>
	<div class="ext-neowiki-display-rule-list">
		<div class="ext-neowiki-display-rule-list__header">
			<span class="ext-neowiki-display-rule-list__header__count">
				{{ $i18n( 'neowiki-layout-editor-shown-count', shownCount, totalCount ).text() }}
			</span>
			<span
				v-if="isDefault"
				class="ext-neowiki-display-rule-list__header__note"
			>
				{{ $i18n( 'neowiki-layout-display-no-rules' ).text() }}
			</span>
			<CdxButton
				v-if="hasHidden"
				class="ext-neowiki-display-rule-list__reset"
				weight="quiet"
				@click="onShowAll"
			>
				{{ $i18n( 'neowiki-layout-editor-show-all-properties' ).text() }}
			</CdxButton>
		</div>
		<ul
			ref="listRef"
			class="ext-neowiki-display-rule-list__items"
		>
			<li
				v-for="row in rows"
				:key="row.property.name.toString()"
				class="ext-neowiki-display-rule-list__item"
				:class="{ 'ext-neowiki-display-rule-list__item--hidden': !row.shown }"
			>
				<span
					v-if="row.shown"
					class="ext-neowiki-display-rule-list__item__drag-handle"
				>
					<CdxIcon
						:icon="cdxIconDraggable"
						:aria-hidden="true"
					/>
				</span>
				<span
					v-else
					class="ext-neowiki-display-rule-list__item__drag-placeholder"
					:aria-hidden="true"
				>
					<CdxIcon :icon="cdxIconDraggable" />
				</span>
				<span
					class="ext-neowiki-display-rule-list__item__action-tooltip"
					:title="$i18n( toggleMessageKey( row.shown ) ).text()"
				>
					<CdxButton
						class="ext-neowiki-display-rule-list__item__action"
						weight="quiet"
						:disabled="lastShown( row.shown )"
						:aria-label="$i18n( toggleMessageKey( row.shown ) ).text()"
						@click="onToggle( row.property.name.toString() )"
					>
						<CdxIcon :icon="row.shown ? cdxIconEye : cdxIconEyeClosed" />
					</CdxButton>
				</span>
				<span class="ext-neowiki-display-rule-list__item__name">
					{{ row.property.name.toString() }}
				</span>
			</li>
		</ul>
	</div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconDraggable, cdxIconEye, cdxIconEyeClosed } from '@wikimedia/codex-icons';
import type { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';
import { useSortable } from '@/composables/useSortable.ts';
import { unifiedRows, rulesAfterToggle, rulesAfterShowingAll, rulesAfterReorder } from './displayRuleEditing.ts';

const props = defineProps<{
	schemaProperties: PropertyDefinition[];
	displayRules: DisplayRule[];
}>();

const emit = defineEmits<{
	'update:display-rules': [ rules: DisplayRule[] ];
}>();

const listRef = ref<HTMLElement | null>( null );

const rows = computed( () => unifiedRows( props.schemaProperties, props.displayRules ) );
const isDefault = computed( () => props.displayRules.length === 0 );
const shownCount = computed( () => rows.value.filter( ( row ) => row.shown ).length );
const totalCount = computed( () => props.schemaProperties.length );
const hasHidden = computed( () => shownCount.value < totalCount.value );

function onToggle( name: string ): void {
	emit( 'update:display-rules', rulesAfterToggle( props.schemaProperties, props.displayRules, name ) );
}

function onShowAll(): void {
	emit( 'update:display-rules', rulesAfterShowingAll( props.schemaProperties, props.displayRules ) );
}

function toggleMessageKey( shown: boolean ): string {
	if ( lastShown( shown ) ) {
		return 'neowiki-layout-editor-keep-one-shown';
	}

	return shown ? 'neowiki-layout-editor-hide-property' : 'neowiki-layout-editor-show-property';
}

function lastShown( shown: boolean ): boolean {
	return shown && shownCount.value === 1;
}

useSortable( listRef, {
	handle: '.ext-neowiki-display-rule-list__item__drag-handle',
	// Only shown rows take part in sorting. Hidden rows have no handle (can't be
	// grabbed) and excluding them here stops a shown row from being dropped into
	// the hidden region, where it would otherwise snap back on the next render.
	draggable: '.ext-neowiki-display-rule-list__item:not(.ext-neowiki-display-rule-list__item--hidden)',
	ghostClass: 'ext-neowiki-display-rule-list__item--ghost',
	onReorder( oldIndex: number, newIndex: number ): void {
		emit( 'update:display-rules', rulesAfterReorder( props.schemaProperties, props.displayRules, oldIndex, newIndex ) );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-display-rule-list {
	&__header {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		padding: @spacing-25 @spacing-75;
		font-size: @font-size-small;
		color: @color-subtle;

		&__count {
			font-weight: @font-weight-bold;
		}

		&__note {
			flex-grow: 1;
		}
	}

	&__reset {
		margin-inline-start: auto;
	}

	&__items {
		list-style: none;
		margin: 0;
		padding: 0;
	}

	&__item {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		padding: @spacing-50 @spacing-75;
		border-radius: @border-radius-base;
		user-select: none;

		&--ghost {
			opacity: @opacity-low;
			background-color: @background-color-interactive-subtle;
		}

		&:hover {
			background-color: @background-color-interactive-subtle;
		}

		&--hidden &__name {
			opacity: @opacity-medium;
		}

		&__name {
			flex-grow: 1;
		}

		&__drag-handle,
		&__drag-placeholder {
			display: inline-flex;
			align-items: center;
			justify-content: center;

			.cdx-icon {
				color: @color-placeholder;
			}
		}

		&__drag-placeholder {
			visibility: hidden;
		}

		&__drag-handle {
			opacity: @opacity-transparent;
			transition: opacity @transition-duration-medium @transition-timing-function-system;
			cursor: grab;

			.ext-neowiki-display-rule-list__item:hover &,
			.ext-neowiki-display-rule-list__item:has( :focus-visible ) & {
				// :has( :focus-visible ) rather than :focus-within: keyboard tabbing must reveal the
				// drag handle, but a mouse click also focuses the control it hits and would keep the
				// handle pinned visible after the pointer leaves.
				opacity: @opacity-base;
			}
		}

		&__action-tooltip {
			display: inline-flex;
			flex-shrink: 0;

			// A disabled button receives no pointer events, so the browser never
			// surfaces its title tooltip on hover. Letting events fall through to
			// this wrapper makes its title the hover target, so the "at least one
			// must stay shown" explanation shows even while the toggle is disabled.
			.cdx-button:disabled {
				pointer-events: none;
			}
		}
	}
}
</style>
