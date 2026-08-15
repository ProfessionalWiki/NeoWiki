<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<CdxMessage
		v-if="errors.length > 0"
		type="error"
	>
		<ul class="ext-neowiki-violation-banners__list">
			<li
				v-for="( violation, index ) in errors"
				:key="index"
			>
				{{ format( violation ) }}
			</li>
		</ul>
	</CdxMessage>

	<CdxMessage
		v-if="warnings.length > 0"
		type="warning"
	>
		<ul class="ext-neowiki-violation-banners__list">
			<li
				v-for="( violation, index ) in warnings"
				:key="index"
			>
				{{ format( violation ) }}
			</li>
		</ul>
	</CdxMessage>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxMessage } from '@wikimedia/codex';
import type { SubjectViolation } from '@/domain/SubjectViolation';

/**
 * The aggregate banners of the subject dialogs: violations that no rendered
 * field displays (subject-level ones, and ones on properties missing from the
 * Schema), split into an error banner and a warning banner so an advisory
 * finding does not read as a blocker.
 */
const props = defineProps<{
	violations: readonly SubjectViolation[];
}>();

const errors = computed( () => props.violations.filter( ( v ) => v.severity === 'error' ) );
const warnings = computed( () => props.violations.filter( ( v ) => v.severity === 'warning' ) );

function format( violation: SubjectViolation ): string {
	return mw.message( `neowiki-field-${ violation.code }`, ...( violation.args as string[] ) ).text();
}
</script>
