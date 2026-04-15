export function minExceedsMax( minValue: string, maxValue: string ): boolean {
	const min = minValue === '' ? undefined : Number( minValue );
	const max = maxValue === '' ? undefined : Number( maxValue );
	return min !== undefined && max !== undefined && min > max;
}
