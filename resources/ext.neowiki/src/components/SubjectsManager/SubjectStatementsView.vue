<template>
	<div class="ext-neowiki-subject-statements">
		<ul
			v-if="resolvedProperties.length > 0"
			class="ext-neowiki-subject-statements__list"
		>
			<li
				v-for="resolved in resolvedProperties"
				:key="resolved.propertyDefinition.name.toString()"
				class="ext-neowiki-subject-statements__item"
			>
				<span class="ext-neowiki-subject-statements__property">
					{{ resolved.propertyDefinition.name.toString() }}
				</span>
				<span class="ext-neowiki-subject-statements__value">
					<StatementDisplay
						:value="resolved.value"
						:property="resolved.propertyDefinition"
					/>
				</span>
			</li>
		</ul>
		<div
			v-else
			class="ext-neowiki-subject-statements__empty"
		>
			{{ $i18n( 'neowiki-managesubjects-no-statements' ).text() }}
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Subject } from '@/domain/Subject';
import { Schema } from '@/domain/Schema';
import { useSchemaStore } from '@/stores/SchemaStore';
import { resolveDisplayProperties, type ResolvedProperty } from '@/domain/resolveDisplayProperties';
import StatementDisplay from '@/components/Value/StatementDisplay.vue';

const props = defineProps<{
	subject: Subject;
}>();

const schemaStore = useSchemaStore();

const resolvedProperties = computed<ResolvedProperty[]>( () => {
	const schema = schemaStore.schemas.get( props.subject.getSchemaName() ) as Schema | undefined;
	if ( schema === undefined ) {
		return [];
	}
	return resolveDisplayProperties( schema, props.subject as Subject );
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-statements {
	&__list {
		list-style: none;
		display: grid;
		grid-template-columns: minmax( 8rem, max-content ) 1fr;
		gap: @spacing-75 @spacing-100;
		margin: 0;
		padding: 0;
	}

	&__item {
		display: contents;
	}

	&__property {
		font-weight: @font-weight-bold;
		color: @color-subtle;
	}

	&__value {
		margin: 0;
	}

	&__empty {
		color: @color-subtle;
		font-style: italic;
	}
}
</style>
