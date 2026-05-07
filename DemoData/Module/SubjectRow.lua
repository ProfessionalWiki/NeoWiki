local p = {}

-- Inline styles use Codex CSS custom properties so the row picks up the
-- current theme (light / dark) where Codex CSS is loaded; hex fallbacks
-- reproduce the light-mode values otherwise.
local style = table.concat( {
	'display:flex',
	'gap:8px',
	'padding:16px',
	'justify-content:safe center',
	'overflow-x:auto',
	'align-items:flex-start',
	'background:var(--background-color-interactive-subtle, #eaecf0)',
	'border:1px solid var(--border-color-subtle, #c8ccd1)',
	'border-radius:2px',
	'margin-block:1em',
}, ';' )

function p.render( frame )
	local layout = frame.args.layout
	local out = { '<div style="' .. style .. '">' }

	for _, id in ipairs( frame.args ) do
		local view
		if layout then
			view = frame:preprocess( '{{#view:' .. id .. '|' .. layout .. '}}' )
		else
			view = frame:preprocess( '{{#view:' .. id .. '}}' )
		end
		-- flex-shrink:0 keeps each infobox at its natural width so the row
		-- scrolls horizontally on narrow viewports instead of squishing.
		out[#out + 1] = '<div style="flex-shrink:0">' .. view .. '</div>'
	end

	out[#out + 1] = '</div>'
	return table.concat( out )
end

return p
