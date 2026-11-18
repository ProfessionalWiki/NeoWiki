<template>
	<div class="ext-neowiki-bundle-plans">
		<section
			v-for="( plan, index ) in plans"
			:key="plan.bundleName"
			class="ext-neowiki-bundle-plans__bundle"
		>
			<div class="ext-neowiki-bundle-plans__header">
				<div class="ext-neowiki-bundle-plans__summary">
					<strong>{{ plan.bundleName }}</strong>{{ afterName( plan ) }}
				</div>
				<CdxButton
					class="ext-neowiki-bundle-plans__toggle"
					weight="quiet"
					:aria-expanded="isExpanded( index )"
					@click="toggle( index )"
				>
					{{ isExpanded( index ) ?
						$i18n( 'neowiki-bundle-items-hide' ).text() :
						$i18n( 'neowiki-bundle-items-show' ).text() }}
				</CdxButton>
			</div>

			<ImportResults
				v-if="outcomes !== null"
				:plan="plan"
				:outcome="outcomes[ index ]"
				:expanded="isExpanded( index )"
			/>
			<BundlePreview
				v-else
				:model-value="modelValue[ index ]"
				:plan="plan"
				:disabled="disabled"
				:expanded="isExpanded( index )"
				@update:model-value="setChosen( index, $event )"
			/>
		</section>
	</div>
</template>

<script setup lang="ts">
import { reactive } from 'vue';
import { CdxButton } from '@wikimedia/codex';
import type { ImportPlan } from '../application/importPlan.ts';
import type { ImportOutcome } from '../application/importRun.ts';
import { planSummary } from '../presentation/bundleItems.ts';
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

// By position: the bundles whose items are all shown. What needs a decision shows either way.
const expanded = reactive( new Set<number>() );

function isExpanded( index: number ): boolean {
	return expanded.has( index );
}

function toggle( index: number ): void {
	if ( expanded.has( index ) ) {
		expanded.delete( index );
	} else {
		expanded.add( index );
	}
}

/**
 * What the summary line says after the bundle's name: its version, if it has one, and what installing it does.
 */
function afterName( plan: ImportPlan ): string {
	const version = plan.bundle.metadata?.version ?? '';
	const versionText = version === '' ? '' : ' ' + mw.msg( 'neowiki-bundle-version', version );

	return versionText + mw.msg( 'colon-separator' ) + planSummary( plan );
}

function setChosen( index: number, chosen: Set<string> ): void {
	emit( 'update:modelValue', props.modelValue.map( ( set, at ) => at === index ? chosen : set ) );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-bundle-plans {
	&__bundle + &__bundle {
		margin-top: @spacing-75;
	}

	&__header {
		display: flex;
		flex-wrap: wrap;
		align-items: baseline;
		column-gap: @spacing-50;
	}
}
</style>
