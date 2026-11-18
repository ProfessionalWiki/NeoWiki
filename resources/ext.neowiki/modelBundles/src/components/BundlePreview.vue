<template>
	<div class="ext-neowiki-bundle-preview">
		<section
			v-for="section in sections"
			:key="section.type"
			class="ext-neowiki-bundle-preview__section"
		>
			<h3 class="ext-neowiki-bundle-preview__heading">
				{{ $i18n( TYPE_HEADINGS[ section.type ] ).text() }}
			</h3>

			<div
				v-for="group in section.groups"
				:key="group.schema ?? ''"
				class="ext-neowiki-bundle-preview__group"
			>
				<CdxCheckbox
					v-if="tickedTogether( group.items ).length > 0"
					class="ext-neowiki-bundle-preview__group-heading"
					:model-value="tickedTogether( group.items ).every( isSelected )"
					:indeterminate="isPartlySelected( tickedTogether( group.items ) )"
					:disabled="disabled"
					@update:model-value="setSelected( tickedTogether( group.items ), $event )"
				>
					<strong>{{ groupTitle( section.type, group ) }}</strong>
				</CdxCheckbox>
				<p
					v-else
					class="ext-neowiki-bundle-preview__group-heading"
				>
					<strong>{{ groupTitle( section.type, group ) }}</strong>
				</p>

				<div class="ext-neowiki-bundle-preview__items">
					<div
						v-for="item in group.items"
						:key="item.id"
						class="ext-neowiki-bundle-preview__item"
					>
						<CdxCheckbox
							v-if="item.action !== null"
							:model-value="isSelected( item )"
							:disabled="disabled"
							@update:model-value="setSelected( [ item ], $event )"
						>
							{{ itemName( item ) }}
							<template #description>
								{{ importStatusText( item, plan.bundleName !== null ) }}
								<span
									v-for="note in importNotes( item )"
									:key="note"
									class="ext-neowiki-bundle-preview__note"
								>
									{{ note }}
								</span>
							</template>
						</CdxCheckbox>
						<p
							v-else
							class="ext-neowiki-bundle-preview__static-item"
						>
							<span class="ext-neowiki-bundle-preview__name">{{ itemName( item ) }}</span>
							<span class="ext-neowiki-bundle-preview__status">{{ importStatusText( item, plan.bundleName !== null ) }}</span>
							<span
								v-for="note in importNotes( item )"
								:key="note"
								class="ext-neowiki-bundle-preview__note"
							>
								{{ note }}
							</span>
						</p>
						<ItemChanges :item="item" />
					</div>

					<details
						v-if="group.quietItems.length > 0"
						class="ext-neowiki-bundle-preview__quiet"
					>
						<summary>
							{{ $i18n( 'neowiki-bundle-preview-quiet', group.quietItems.length ).text() }}
						</summary>
						<div
							v-for="item in group.quietItems"
							:key="item.id"
							class="ext-neowiki-bundle-preview__item"
						>
							<p class="ext-neowiki-bundle-preview__static-item">
								<span class="ext-neowiki-bundle-preview__name">{{ itemName( item ) }}</span>
								<span class="ext-neowiki-bundle-preview__status">{{ importStatusText( item, plan.bundleName !== null ) }}</span>
								<span
									v-for="note in importNotes( item )"
									:key="note"
									class="ext-neowiki-bundle-preview__note"
								>
									{{ note }}
								</span>
							</p>
							<ItemChanges :item="item" />
						</div>
					</details>
				</div>
			</div>
		</section>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxCheckbox } from '@wikimedia/codex';
import type { ItemType } from '../application/ModelBundle.ts';
import type { ImportItem, ImportPlan } from '../application/importPlan.ts';
import {
	importNotes,
	importStatusText,
	itemName,
	previewSections,
	type PreviewGroup,
	TYPE_HEADINGS
} from '../presentation/bundleItems.ts';
import ItemChanges from './ItemChanges.vue';

const props = defineProps<{
	plan: ImportPlan;
	modelValue: ReadonlySet<string>;
	disabled: boolean;
}>();

const emit = defineEmits<{
	'update:modelValue': [ chosen: Set<string> ];
}>();

const sections = computed( () => previewSections( props.plan.items ) );

function groupTitle( type: ItemType, group: PreviewGroup ): string {
	const count = group.items.length + group.quietItems.length;

	return type === 'subject' ?
		mw.msg( 'neowiki-bundle-preview-schema-group', group.schema ?? '', count ) :
		mw.msg( 'neowiki-bundle-preview-all', count );
}

/**
 * A group's heading ticks what may be written, but never a removal: that takes ticking the item itself.
 */
function tickedTogether( items: readonly ImportItem[] ): ImportItem[] {
	return items.filter( ( item ) => item.action !== null && item.action !== 'remove' );
}

function isSelected( item: ImportItem ): boolean {
	return props.modelValue.has( item.id );
}

function isPartlySelected( items: ImportItem[] ): boolean {
	return items.some( isSelected ) && !items.every( isSelected );
}

function setSelected( items: ImportItem[], selected: boolean ): void {
	const chosen = new Set( props.modelValue );
	items.forEach( ( item ) => selected ? chosen.add( item.id ) : chosen.delete( item.id ) );
	emit( 'update:modelValue', chosen );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-bundle-preview {
	&__section + &__section {
		margin-top: @spacing-150;
	}

	&__heading {
		margin: 0 0 @spacing-50;
		font-size: @font-size-large;
	}

	&__group + &__group {
		margin-top: @spacing-100;
	}

	&__group-heading {
		margin: 0;
	}

	&__items {
		padding-inline-start: calc( @min-size-input-binary + @spacing-50 );
	}

	&__item {
		margin-top: @spacing-25;
	}

	&__static-item {
		margin: 0;
	}

	&__status {
		margin-inline-start: @spacing-50;
		color: @color-subtle;
	}

	&__note {
		display: block;
	}

	&__quiet {
		margin-top: @spacing-50;

		summary {
			cursor: pointer;
			color: @color-subtle;
		}
	}
}
</style>
