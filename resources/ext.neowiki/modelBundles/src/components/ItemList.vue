<template>
	<div class="ext-neowiki-item-list">
		<section
			v-for="section in sections"
			:key="section.type"
			class="ext-neowiki-item-list__section"
		>
			<h4 class="ext-neowiki-item-list__heading">
				{{ $i18n( TYPE_HEADINGS[ section.type ] ).text() }}
			</h4>
			<div
				v-for="group in section.groups"
				:key="group.schema ?? ''"
				class="ext-neowiki-item-list__group"
			>
				<p
					v-if="group.schema !== null"
					class="ext-neowiki-item-list__schema"
				>
					{{ group.schema }}
				</p>
				<ul class="ext-neowiki-item-list__items">
					<li
						v-for="item in group.items"
						:key="item.key"
					>
						<span v-if="item.status === 'deleted-here'">{{ itemName( item ) }}</span>
						<a
							v-else
							:href="urlOf( item )"
						>{{ itemName( item ) }}</a>
						<span
							v-if="item.status !== undefined"
							class="ext-neowiki-item-list__status"
							:class="`ext-neowiki-item-list__status--${ item.status }`"
						>
							{{ $i18n( STATUS_MESSAGES[ item.status ] ).text() }}
						</span>
					</li>
				</ul>
			</div>
		</section>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { ItemType } from '../application/ModelBundle.ts';
import type { DataModelItem } from '../application/bundleStatus.ts';
import { itemName, itemSections, TYPE_HEADINGS } from '../presentation/bundleItems.ts';
import { subjectPageUrl } from 'ext.neowiki';

const props = defineProps<{
	items: readonly DataModelItem[];
}>();

const STATUS_MESSAGES = {
	unchanged: 'neowiki-bundle-status-unchanged',
	'changed-here': 'neowiki-bundle-status-changed-here',
	'deleted-here': 'neowiki-bundle-status-deleted-here',
	unreadable: 'neowiki-bundle-status-unreadable'
} as const;

const PAGE_PREFIXES: Record<Exclude<ItemType, 'subject'>, string> = {
	schema: 'Schema:',
	layout: 'Layout:',
	mapping: 'Mapping:'
};

const sections = computed( () => itemSections( props.items ) );

function urlOf( item: DataModelItem ): string {
	return item.type === 'subject' ? subjectPageUrl( item.key ) : mw.util.getUrl( PAGE_PREFIXES[ item.type ] + item.key );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-item-list {
	&__section + &__section {
		margin-top: @spacing-100;
	}

	&__heading {
		margin: 0 0 @spacing-25;
	}

	&__schema {
		margin: @spacing-50 0 0;
		font-weight: @font-weight-bold;
	}

	&__items {
		margin: 0 0 0 @spacing-125;
	}

	&__status {
		margin-inline-start: @spacing-50;
		color: @color-subtle;

		&--changed-here {
			color: @color-warning;
		}

		&--deleted-here,
		&--unreadable {
			color: @color-error;
		}
	}
}
</style>
