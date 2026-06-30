<template>
	<div class="ext-neowiki-unknown-value">
		<span class="ext-neowiki-unknown-value__raw">{{ rawText }}</span>
		<span class="ext-neowiki-unknown-value__note">{{ note }}</span>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { ValueType } from '@/domain/Value.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { formatRawValue } from '@/presentation/formatRawValue.ts';
import { notifyUnknownPropertyType } from '@/presentation/notifyUnknownPropertyType.ts';

const props = defineProps<ValueDisplayProps<PropertyDefinition>>();

const unknownValue = computed( () =>
	props.value.type === ValueType.Unknown ? props.value : undefined );

const typeName = computed( (): string => unknownValue.value?.typeName ?? props.property.type );

const rawText = computed( (): string => formatRawValue( unknownValue.value?.raw ) );

const note = computed( (): string => mw.msg( 'neowiki-property-type-unknown-note', typeName.value ) );

onMounted( () => notifyUnknownPropertyType( typeName.value ) );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-unknown-value {
	&__raw {
		overflow-wrap: anywhere;
		word-break: break-word;
	}

	&__note {
		display: block;
		margin-top: @spacing-25;
		color: @color-subtle;
		font-size: @font-size-small;
	}
}
</style>
