local testframework = require 'Module:TestFramework'
local nw = require( 'mw.neowiki' )

local restrictedPage = 'NeoWikiLuaRestrictedPage'
local openPage = 'NeoWikiLuaTestPage'

local function testGetValueOnRestrictedPageIsNil()
	return nw.getValue( 'City', { page = restrictedPage } )
end

local function testGetValueOnOpenPageStillReads()
	return nw.getValue( 'City', { page = openPage } )
end

local function testGetMainSubjectOnRestrictedPageIsNil()
	return nw.getMainSubject( restrictedPage )
end

local function testGetChildSubjectsOnRestrictedPageIsEmpty()
	return #nw.getChildSubjects( restrictedPage )
end

local function testGetSchemaOnRestrictedPageIsNil()
	return nw.getSchema( 'RestrictedSchema' )
end

local function testGetSchemaOnOpenPageStillReads()
	return nw.getSchema( 'Employee' ) ~= nil
end

-- The full sentence, not a fragment: the generic fallback message would carry the service's
-- "You do not have permission to run queries." as its detail and match a fragment too.
local function isDeniedWith( message, call )
	local ok, err = pcall( call )
	if ok then
		return 'unexpected success'
	end
	return type( err ) == 'string' and string.find( err, message, 1, true ) ~= nil
end

local function testQueryIsDeniedWithoutTheRight()
	return isDeniedWith( 'You do not have permission to run Cypher queries.', function()
		return nw.query( 'RETURN 1 AS n' )
	end )
end

local function testSparqlQueryIsDeniedWithoutTheRight()
	return isDeniedWith( 'You do not have permission to run SPARQL queries.', function()
		return nw.sparqlQuery( 'SELECT * WHERE { ?s ?p ?o }' )
	end )
end

local tests = {
	{ name = 'getValue returns nil for a page the parsing user may not read',
	  func = testGetValueOnRestrictedPageIsNil, expect = { nil } },
	{ name = 'getValue still reads a page the parsing user may read',
	  func = testGetValueOnOpenPageStillReads, expect = { 'Berlin' } },
	{ name = 'getMainSubject returns nil for a page the parsing user may not read',
	  func = testGetMainSubjectOnRestrictedPageIsNil, expect = { nil } },
	{ name = 'getChildSubjects returns nothing for a page the parsing user may not read',
	  func = testGetChildSubjectsOnRestrictedPageIsEmpty, expect = { 0 } },
	{ name = 'getSchema returns nil for a Schema page the parsing user may not read',
	  func = testGetSchemaOnRestrictedPageIsNil, expect = { nil } },
	{ name = 'getSchema still reads a Schema page the parsing user may read',
	  func = testGetSchemaOnOpenPageStillReads, expect = { true } },
	{ name = 'query is denied without the neowiki-query right',
	  func = testQueryIsDeniedWithoutTheRight, expect = { true } },
	{ name = 'sparqlQuery is denied without the neowiki-query right',
	  func = testSparqlQueryIsDeniedWithoutTheRight, expect = { true } },
}

return testframework.getTestProvider( tests )
