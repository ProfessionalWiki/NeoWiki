<template>
	<div class="infobox">
		<div class="infobox-title">
			{{ title }}
		</div>
		<div class="infobox-statements">
			<div
				v-for="( statement, index ) in statements"
				:key="index"
				class="infobox-statement">
				<div class="infobox-statement-property">
					{{ statement.property }}
				</div>
				<div class="infobox-statement-value">
					{{ statement.value }}
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
defineProps<{
	title: string;
	statements?: { property: string; value: string }[];
}>();
</script>

<style scoped>
.infobox {
	border: 1px solid #000;
	max-width: 300px;
}

.infobox-title {
	text-align: center;
}

.infobox-statement {
	display: flex;
}
</style>
