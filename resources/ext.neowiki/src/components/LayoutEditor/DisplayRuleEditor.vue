<template>
	<div class="ext-neowiki-display-rule-editor">
		<div class="ext-neowiki-display-rule-editor__heading">
			<CdxButton
				class="ext-neowiki-display-rule-editor__back"
				weight="quiet"
				:aria-label="$i18n( 'neowiki-layout-editor-back-to-properties' ).text()"
				:title="$i18n( 'neowiki-layout-editor-back-to-properties' ).text()"
				@click="emit( 'back' )"
			>
				<CdxIcon :icon="cdxIconArrowPrevious" />
			</CdxButton>
			<h3 class="ext-neowiki-display-rule-editor__name">
				{{ property.name.toString() }}
			</h3>
		</div>

		<div ref="fields">
			<component
				:is="componentRegistry.getDisplayAttributesEditor( property.type )"
				:key="property.name.toString()"
				:property="property"
				:display-attributes="displayAttributes"
				@update:display-attributes="emit( 'update:displayAttributes', $event )"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconArrowPrevious } from '@wikimedia/codex-icons';
import type { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { focusFirstValueControl } from '@/components/common/valueControlFocus.ts';

defineProps<{
	property: PropertyDefinition;
	displayAttributes: Record<string, unknown>;
}>();

const emit = defineEmits<{
	'update:displayAttributes': [ patch: Record<string, unknown> ];
	'back': [];
}>();

const componentRegistry = NeoWikiServices.getComponentRegistry();

const fields = ref<HTMLElement | null>( null );

export interface DisplayRuleEditorExposes {
	focusFirstField: () => void;
}

defineExpose( {
	focusFirstField(): void {
		focusFirstValueControl( fields.value! );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-display-rule-editor {
	display: flex;
	flex-direction: column;
	gap: @spacing-100;

	&__heading {
		display: flex;
		align-items: center;
		gap: @spacing-25;
	}

	&__name {
		margin: 0;
		padding: 0;
		font-size: @font-size-medium;
		font-weight: @font-weight-bold;
	}
}
</style>
