local p = {}
local nw = require( 'mw.neowiki' )

function p.foundedYear( frame )
	local year = nw.getValue( 'Founded', { page = frame.args[1] } )
	return tostring( year or '' )
end

function p.oldestMuseums( frame )
	local rows = nw.query(
		'MATCH (m:Museum) RETURN m.name AS name, m.Founded AS year ORDER BY year LIMIT 3'
	)

	local list = {}
	for _, row in ipairs( rows ) do
		list[#list + 1] = '* ' .. row.name .. ' (' .. row.year .. ')'
	end
	return table.concat( list, '\n' )
end

return p
