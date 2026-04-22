<template>
	<div class="ext-neowiki-subject-statements">
		<dl
			v-if="resolvedProperties.length > 0"
			class="ext-neowiki-subject-statements__list"
		>
			<div
				v-for="resolved in resolvedProperties"
				:key="resolved.propertyDefinition.name.toString()"
				class="ext-neowiki-subject-statements__item"
			>
				<dt class="ext-neowiki-subject-statements__property">
					{{ resolved.propertyDefinition.name.toString() }}
				</dt>
				<dd class="ext-neowiki-subject-statements__value">
					<StatementDisplay
						:value="resolved.value"
						:property="resolved.propertyDefinition"
					/>
				</dd>
			</div>
		</dl>
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
	line-height: @line-height-small;

	&__list {
		margin: 0;
		padding: 0;
	}

	&__item {
		display: flex;
		flex-direction: column;
		gap: @spacing-25;
		padding-block: @spacing-75;
		border-top: @border-subtle;
	}

	&__item:first-child {
		border-top: 0;
	}

	&__property {
		font-weight: @font-weight-bold;
		color: @color-emphasized;
	}

	&__value {
		margin: 0;
		overflow-wrap: anywhere;
		word-break: break-word;
	}

	&__empty {
		color: @color-subtle;
		font-style: italic;
	}

	@media ( min-width: @min-width-breakpoint-tablet ) {
		&__item {
			flex-direction: row;
			align-items: flex-start;
			column-gap: @spacing-150;
		}

		&__property {
			flex: 0 0 40%;
		}

		&__value {
			flex: 0 1 60%;
		}
	}
}
</style>
