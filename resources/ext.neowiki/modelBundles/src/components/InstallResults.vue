<template>
	<div class="ext-neowiki-install-results">
		<CdxMessage :type="line.type">
			{{ line.text }}
			<span
				v-if="line.note !== null"
				class="ext-neowiki-install-results__note"
			>
				{{ line.note }}
			</span>
			<ul
				v-if="failures.length > 0"
				class="ext-neowiki-install-results__failures"
			>
				<li
					v-for="failure in failures"
					:key="failure.item.id"
				>
					{{ itemName( failure.item ) }}
					<span class="ext-neowiki-install-results__type">{{ $i18n( TYPE_NOUNS[ failure.item.type ] ).text() }}</span>
					<span class="ext-neowiki-install-results__reason">{{ failure.reason }}</span>
				</li>
			</ul>
		</CdxMessage>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxMessage } from '@wikimedia/codex';
import type { InstallPlan } from '../application/installPlan.ts';
import type { InstallOutcome } from '../application/installRun.ts';
import { itemName, resultLine } from '../presentation/bundleItems.ts';

const props = defineProps<{
	plan: InstallPlan;
	outcome: InstallOutcome;
}>();

const TYPE_NOUNS = {
	schema: 'neowiki-schema-noun',
	layout: 'neowiki-layout-noun',
	mapping: 'neowiki-mapping-noun',
	subject: 'neowiki-bundle-subject-noun'
} as const;

const line = computed( () => resultLine( props.plan, props.outcome ) );
const failures = computed( () => props.outcome.items.filter( ( itemOutcome ) => itemOutcome.result === 'failed' ) );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-install-results {
	&__note {
		display: block;
	}

	&__failures {
		margin: @spacing-50 0 0 @spacing-125;
	}

	&__type {
		margin-inline-start: @spacing-25;
		color: @color-subtle;
	}

	&__reason {
		display: block;
	}
}
</style>
