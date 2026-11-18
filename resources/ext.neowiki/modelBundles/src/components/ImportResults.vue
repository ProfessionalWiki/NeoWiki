<template>
	<div class="ext-neowiki-import-results">
		<CdxMessage
			v-if="headline !== null"
			:type="headline.type"
			class="ext-neowiki-import-results__headline"
		>
			{{ headline.text }}
			<span
				v-if="headline.note !== null"
				class="ext-neowiki-import-results__note"
			>
				{{ headline.note }}
			</span>
		</CdxMessage>

		<p v-if="outcome.items.length === 0">
			{{ $i18n( 'neowiki-bundle-result-nothing' ).text() }}
		</p>

		<section
			v-for="group in groups"
			:key="group.result"
			class="ext-neowiki-import-results__group"
		>
			<h3 class="ext-neowiki-import-results__heading">
				{{ $i18n( RESULT_HEADINGS[ group.result ], group.outcomes.length ).text() }}
			</h3>
			<ul
				v-if="expanded || group.result === 'failed'"
				class="ext-neowiki-import-results__list"
			>
				<li
					v-for="itemOutcome in group.outcomes"
					:key="itemOutcome.item.id"
				>
					{{ itemName( itemOutcome.item ) }}
					<span class="ext-neowiki-import-results__type">{{ $i18n( TYPE_NOUNS[ itemOutcome.item.type ] ).text() }}</span>
					<CdxMessage
						v-if="itemOutcome.reason !== null"
						type="error"
						:inline="true"
					>
						{{ itemOutcome.reason }}
					</CdxMessage>
				</li>
			</ul>
		</section>

		<CdxMessage
			v-if="!outcome.record.saved"
			type="error"
			class="ext-neowiki-import-results__record-failure"
		>
			{{ $i18n( 'neowiki-bundle-record-failed', plan.bundleName, outcome.record.reason ).text() }}
		</CdxMessage>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxMessage } from '@wikimedia/codex';
import type { ImportPlan } from '../application/importPlan.ts';
import type { ImportOutcome } from '../application/importRun.ts';
import { itemName, resultGroups, resultHeadline } from '../presentation/bundleItems.ts';

const props = defineProps<{
	plan: ImportPlan;
	outcome: ImportOutcome;
	/** Whether to list every item, rather than only those that failed. */
	expanded: boolean;
}>();

const RESULT_HEADINGS = {
	failed: 'neowiki-bundle-result-failed',
	created: 'neowiki-bundle-result-created',
	replaced: 'neowiki-bundle-result-replaced',
	removed: 'neowiki-bundle-result-removed'
} as const;

const TYPE_NOUNS = {
	schema: 'neowiki-schema-noun',
	layout: 'neowiki-layout-noun',
	mapping: 'neowiki-mapping-noun',
	subject: 'neowiki-bundle-subject-noun'
} as const;

const headline = computed( () => resultHeadline( props.plan, props.outcome ) );
const groups = computed( () => resultGroups( props.outcome.items ) );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-import-results {
	margin-top: @spacing-50;

	&__group + &__group {
		margin-top: @spacing-100;
	}

	&__heading {
		margin: 0 0 @spacing-25;
		font-size: @font-size-medium;
	}

	&__list {
		margin: 0 0 0 @spacing-125;
	}

	&__type {
		margin-inline-start: @spacing-25;
		color: @color-subtle;
	}

	&__headline {
		margin-bottom: @spacing-150;
	}

	&__note {
		display: block;
	}

	&__record-failure {
		margin-top: @spacing-150;
	}
}
</style>
