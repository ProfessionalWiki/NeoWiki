local p = {}
local nw = require( 'mw.neowiki' )

function p.foundedYear( frame )
	local year = nw.getValue( 'Founded', { page = frame.args[1] } )
	return tostring( year or '' )
end

-- Needs a SPARQL store: on a wiki without one, mw.neowiki.sparqlQuery is nil.
function p.largestMuseums( frame )
	local base = mw.site.server
	local result = nw.sparqlQuery(
		'SELECT ?label ?visitors WHERE { ' ..
		'?m <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <' .. base .. '/schema/Museum> . ' ..
		'?m <http://www.w3.org/2000/01/rdf-schema#label> ?label . ' ..
		'?m <' .. base .. '/prop/Annual_visitors> ?visitors ' ..
		'} ORDER BY DESC(?visitors) LIMIT 3'
	)

	local list = {}
	for _, binding in ipairs( result.results.bindings ) do
		list[#list + 1] = '* ' .. binding.label.value .. ' (' .. binding.visitors.value .. ' visitors)'
	end
	return table.concat( list, '\n' )
end

return p
