<template>
	<div class="ext-neowiki-bundle-plans">
		<section
			v-for="( plan, index ) in plans"
			:key="plan.bundleName"
			class="ext-neowiki-bundle-plans__bundle"
		>
			<h3
				v-if="plans.length > 1"
				class="ext-neowiki-bundle-plans__name"
			>
				{{ plan.bundleName }}
				<span v-if="plan.bundle.metadata !== null && plan.bundle.metadata.version !== ''">
					{{ $i18n( 'neowiki-bundle-version', plan.bundle.metadata.version ).text() }}
				</span>
			</h3>

			<ImportResults
				v-if="outcomes !== null"
				:plan="plan"
				:outcome="outcomes[ index ]"
			/>
			<BundlePreview
				v-else
				:model-value="modelValue[ index ]"
				:plan="plan"
				:disabled="disabled"
				@update:model-value="setChosen( index, $event )"
			/>
		</section>
	</div>
</template>

<script setup lang="ts">
import type { ImportPlan } from '../application/importPlan.ts';
import type { ImportOutcome } from '../application/importRun.ts';
import BundlePreview from './BundlePreview.vue';
import ImportResults from './ImportResults.vue';

const props = defineProps<{
	/** One for each bundle, in the order they are installed. */
	plans: readonly ImportPlan[];
	/** Once they ran: one for each plan. */
	outcomes: readonly ImportOutcome[] | null;
	/** What is ticked, for each plan. */
	modelValue: readonly Set<string>[];
	disabled: boolean;
}>();

const emit = defineEmits<{
	'update:modelValue': [ chosen: Set<string>[] ];
}>();

function setChosen( index: number, chosen: Set<string> ): void {
	emit( 'update:modelValue', props.modelValue.map( ( set, at ) => at === index ? chosen : set ) );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-bundle-plans {
	&__bundle + &__bundle {
		margin-top: @spacing-200;
	}

	&__name {
		margin: 0 0 @spacing-75;

		span {
			margin-inline-start: @spacing-50;
			color: @color-subtle;
			font-weight: @font-weight-normal;
		}
	}
}
</style>
