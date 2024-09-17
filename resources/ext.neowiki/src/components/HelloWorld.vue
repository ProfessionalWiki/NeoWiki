<template>
	<div>
		<h1>{{ $i18n( 'neowiki-name' ).text() }}: {{ msg }}</h1>

		<CdxButton
			action="progressive"
			@click="count++"
		>
			<CdxIcon :icon="cdxIconAdd" />
			count is {{ count }}
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';

defineProps<{ msg: string }>();

const count = ref( 0 );
</script>

<style lang="scss" scoped>
@import '@/assets/scss/variables';

.read-the-docs {
	color: $neo-secondary;

	&:hover {
		color: brightness( $neo-secondary, 10% );
	}
}
</style>
