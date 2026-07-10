<template>
	<div class="ext-neowiki-unregistered-type-value">
		<span class="ext-neowiki-unregistered-type-value__raw">{{ rawText }}</span>
		<span class="ext-neowiki-unregistered-type-value__note">{{ note }}</span>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { ValueType } from '@/domain/Value.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { formatRawValue } from '@/presentation/formatRawValue.ts';
import { notifyUnregisteredPropertyType } from '@/presentation/notifyUnregisteredPropertyType.ts';

const props = defineProps<ValueDisplayProps<PropertyDefinition>>();

const unregisteredTypeValue = computed( () =>
	props.value.type === ValueType.UnregisteredType ? props.value : undefined );

const typeName = computed( (): string => unregisteredTypeValue.value?.typeName ?? props.property.type );

const rawText = computed( (): string => formatRawValue( unregisteredTypeValue.value?.raw ) );

const note = computed( (): string => mw.msg( 'neowiki-property-type-unregistered-note', typeName.value ) );

onMounted( () => notifyUnregisteredPropertyType( typeName.value ) );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-unregistered-type-value {
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
