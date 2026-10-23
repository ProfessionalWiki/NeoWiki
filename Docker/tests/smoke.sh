#! /bin/bash

STATUS=0

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
. "$SCRIPT_DIR/../.env"

function echoGood {
    echo -e "\e[32mSuccess: $1\e[0m"
}

function echoBad {
    echo -e "\e[31mFail: $1\e[0m"
}

function testContains {
    if echo "$1" | grep "$2" > /dev/null
    then
        echoGood "$3"
    else
        echoBad "$3"
        STATUS=1
    fi
}

function testDoesNotContain {
    if echo "$1" | grep "$2" > /dev/null
    then
        echoBad "$3"
        STATUS=1
    else
        echoGood "$3"
    fi
}

function sparqlQuery {
    curl -sS -X POST -H 'Content-Type: application/json' --data "{\"query\": \"$1\"}" \
        "http://localhost:${MW_SERVER_PORT}/w/rest.php/neowiki/v0/query/sparql"
}

mainPage=`curl -fsSL http://localhost:${MW_SERVER_PORT}/index.php/Main_Page`

testContains "$mainPage" "ext-neowiki-create-subject-button" "Main Page has NeoWiki's starter content"

apiPhp=`curl -fsSL http://localhost:${MW_SERVER_PORT}/api.php`

testContains "$apiPhp" "/api.php?action=" "Can reach api.php"

testContains "$(sparqlQuery 'SELECT * WHERE { ?s ?p ?o } LIMIT 1')" '"results"' "The SPARQL store answers queries"

# The store follows every SERVICE clause, so only its network keeps a query from reaching other hosts.
federated=$(sparqlQuery 'SELECT * WHERE { SERVICE <https://query.wikidata.org/sparql> { SELECT ?x WHERE { BIND(1 AS ?x) } } }')
testDoesNotContain "$federated" '"bindings":\[{' "The SPARQL store cannot reach the internet"

exit $STATUS
