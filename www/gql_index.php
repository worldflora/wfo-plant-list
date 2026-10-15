<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');
?>

<h1>GraphQL API</h1>

<p>The <a href="https://graphql.org/">GraphQL</a> endpoint is <a href="<?php echo get_uri('gql.php') ?>"><?php echo get_uri('gql.php') ?></a>.</p>

<p>
    There are many resources on the web about use of GraphQL. It enables self documenting APIs and all the objects and properties available here have been documented. 
    The use of a GraphQL client or IDE are recommended e.g. the GraphiQL plugin for Google Chrome.
</p>

<p>
    You don't need fancy libraries to access the GraphQL end point it and it might be the best approach for embedding the WFO Plant List in your project. 
    Here are some examples of how to use the API with plain JavaScript.
</p>

    <script>

        /*
            We will be using the GraphQL API hosted at https://list.worldfloraonline.org/gql

            GraphQL is a very expressive API language which can include full documentation of a graph. 
            You are encouraged to install a GraphQL client, such as Altair Chrome extension, to explore the end point and test out
            different queries.

            Although there are many client and server side GraphQL libraries available they aren't required to run simple queries like we 
            are doing here. It is enough to define a simple utility function that take a GraphQL query as a string, passes it to the 
            API end point and returns the data as JSON. Yippee! We can avoid any dependencies or steep learning curves.

            This is demo code not production code. Extra error catching and edge case handling are exercises left to the reader.

        */

        // hard code the api uri - shouldn't change
        // const graphQlUri = "https://list.worldfloraonline.org/gql";
        const graphQlUri = "<?php echo get_uri("gql.php") ?>";

        /**
         * This calls the Plant List API and returns JSON data 
         * 
         * @param {string} query The GraphQL query to run
         * @param {Object} variables A key/value set of variables to inserted into the query, if specified. 
         */
        function runGraphQuery(query, variables, giveBack) {

            const payload = {
                'query': query,
                'variables': variables
            }

            var options = {
                'method': 'POST',
                'contentType': 'application/json',
                'headers': {},
                'body': JSON.stringify(payload)
            };

            const response = fetch(graphQlUri, options)
                .then((response) => response.json())
                .then((data) => giveBack(data));

            return;
        }

        function getLinkForName($name) {
            return `<a target="wfo_portal" href="${$name.stableUri}">${$name.fullNameStringHtml}</a>`;
        }


    </script>

        <h2>WFO Plant List API Demos</h2>
        <p>Here are some examples that show how data from the <a href="/">WFO
                Plant
                List API</a> can easily be embedded in a web page.</p>
        <p>This one file contains all the code needed to access the API and render the examples below. The code
            is displayed for the first example to give an indication of how it works. For the rest of the examples you
            can use
            "view
            source" to look at it the full working code or view it on <a
                href="https://github.com/rogerhyam/wfo-plant-list/blob/main/www/gql_index.php">GitHub</a>. There are no
            external
            library dependencies. The code is heavily
            commented.</p>

        <p>Although these examples are javascript and browser based they should be simple enough to port to other
            environments.
        </p>

        <p>Examples just demonstrate querying the current taxonomy because that is the most common use case
            but it is possible to navigate backwards
            and forwards in time to see how the treatment of a name has changed.
            It should be possible to recreate the full WFO Plant List functionality as shown in the portal using calls
            to
            the API. Indeed that is how the portal version of the Plant List is implemented.</p>

        <p><strong>Please show us what you can build with this.</strong> Please don't use it to scrape the data as that
            can be
            download
            freely anyway.</p>
        <hr />

        <h2>GraphQL Utility Function</h2>
        <p>This is the utility function used by all examples.</p>
        <pre>
    function runGraphQuery(query, variables, giveBack) {
    
        const payload = {
            'query': query,
            'variables': variables
        }
    
        var options = {
            'method': 'POST',
            'contentType': 'application/json',
            'headers': {},
            'body': JSON.stringify(payload)
        };
    
        const response = fetch(graphQlUri, options)
            .then((response) => response.json())
            .then((data) => giveBack(data));
    
        return;
    }
        </pre>
        <hr />
        <h2>1: Include full name based on WFO ID</h2>
        <p>This will just look up the WFO ID <strong>wfo-0001048766</strong> and render its full name. The WFO ID is
            hard coded like it was written in by the server.</p>

        <pre>
    let query =
    `query{
        taxonNameById(nameId: "wfo-0001048766"){
        fullNameStringHtml
        }
    }`;
    runGraphQuery(query, {}, (response) => document.getElementById("example-01").innerHTML =
    response.data.taxonNameById.fullNameStringHtml);
        </pre>

        <script>

            // define the GraphQL query string.
            // we can develop this separately using a GraphQL client till we get it right. 
            let query =
                `query{
                    taxonNameById(nameId: "wfo-0001048766"){
                        fullNameStringHtml
                    }
                }`;


            // here we call our utility function and pass it
            // the query we just defined
            // an empty object as the query isn't parametised
            // an arrow function (could be any function) that receives the data 
            // here we just write one field to a named node in the DOM, a <p> tag just below here
            runGraphQuery(query, {}, (response) => document.getElementById("example-01").innerHTML = response.data.taxonNameById.fullNameStringHtml);
        </script>

        <p class="output" id="example-01">Loading ...</p>

        <hr />

        <h2>2: Include full name and current taxonomic status. Is it a synonym?</h2>
        <p>Here we extend example #1 to add in the status but fetching the associated taxon.</p>

        <script>

            // define the GraphQL query string.
            // This may seem convoluted at first but is very powerful and quickly becomes natural - honestly!
            // we get the currentPreferredUsage of the name
            // If there is no currentPreferredUsage then we don't know what the correct taxonomic placement of this name is. It is just an unchecked floating name or deprecated error.
            // If currentPreferredUsage exists and has the same name as we started with then it is an accepted name
            // If the currentPreferredUsage name is different then we have a synonym and the currentPreferredUsage name is the accepted one.
            query =
                `query{
                        taxonNameById(nameId: "wfo-0001048766"){
                            id
                            fullNameStringHtml,
                            currentPreferredUsage{
                                hasName{
                                    id
                                }
                            }
                        }
                    }`;


            // here we call our utility function and pass it
            // we flesh out the call back function to do more with the JSON
            runGraphQuery(query, {}, (response) => {

                let target = document.getElementById("example-02")
                let name = response.data.taxonNameById;

                if (name.currentPreferredUsage) {
                    if (name.currentPreferredUsage.hasName.id == name.id) {
                        target.innerHTML = "<strong>Accepted: </strong>" + name.fullNameStringHtml;
                    } else {
                        target.innerHTML = "<strong>Synonym: </strong>" + name.fullNameStringHtml;
                    }
                } else {
                    target.innerHTML = "<strong>Unplaced: </strong>" + name.fullNameStringHtml;
                }

            }

            );
        </script>

        <p class="output" id="example-02">Loading ...</p>

        <hr />

        <h2>3: Include full name and accepted name.</h2>
        <p>Here we extend example #2 to add in the accepted name.</p>

        <script>

            // define the GraphQL query string.
            // This may seem convoluted at first but is very powerful and quickly becomes natural - honestly!
            // we get the currentPreferredUsage of the name
            // If there is no currentPreferredUsage then we don't know what the correct taxonomic placement of this name is. It is just an unchecked floating name or deprecated error.
            // If currentPreferredUsage exists and has the same name as we started with then it is an accepted name
            // If the currentPreferredUsage name is different then we have a synonym and the currentPreferredUsage name is the accepted one.
            query =
                `query{
                    taxonNameById(nameId: "wfo-0001048766"){
                        id
                        fullNameStringHtml,
                        currentPreferredUsage{
                            hasName{
                                id,
                                fullNameStringHtml
                            }
                        }
                    }
                }`;


            // here we call our utility function and pass it
            // we flesh out the call back function to do more with the JSON
            runGraphQuery(query, {}, (response) => {

                let target = document.getElementById("example-03")
                let name = response.data.taxonNameById;

                if (name.currentPreferredUsage) {
                    if (name.currentPreferredUsage.hasName.id == name.id) {
                        target.innerHTML = `<strong>${name.fullNameStringHtml}</strong>`;
                    } else {
                        let accepted_name = name.currentPreferredUsage.hasName;
                        target.innerHTML = `<strong>${accepted_name.fullNameStringHtml}</strong><br/>&nbsp;&nbsp;&nbsp;<strong>syn: </strong>${name.fullNameStringHtml}`;
                    }
                } else {
                    target.innerHTML = "<strong>Unplaced: </strong>" + name.fullNameStringHtml;
                }

            }

            );
        </script>

        <p class="output" id="example-03">Loading ...</p>

        <hr />

        <h2>4: Linking.</h2>
        <p>Probably the most common thing to want to do is link to the WFO portal once we have the name.</p>

        <script>

            // define the GraphQL query string.
            // we use the stableUri property to create the links
            // this will be a redirect via the API for any human web browser but offers full
            // semantic web support if a machine resolves the link.
            query =
                `query{
                            taxonNameById(nameId: "wfo-0001048766"){
                                id,
                                stableUri,
                                fullNameStringHtml,
                                currentPreferredUsage{
                                    stableUri,
                                    hasName{
                                        id,
                                        stableUri,
                                        fullNameStringHtml
                                    }
                                }
                            }
                        }`;


            // here we call our utility function and pass it
            // we flesh out the call back function to do more with the JSON
            runGraphQuery(query, {}, (response) => {

                let target = document.getElementById("example-04")
                let name = response.data.taxonNameById;
                let name_link = getLinkForName(name); // utility function defined above so we don't have to keep building <a> tags.

                if (name.currentPreferredUsage) {
                    if (name.currentPreferredUsage.hasName.id == name.id) {
                        target.innerHTML = `<strong>${name_link}</strong>`;
                    } else {
                        let accepted_link = getLinkForName(name.currentPreferredUsage.hasName);
                        target.innerHTML = `<strong>${accepted_link}</strong><br/>&nbsp;&nbsp;&nbsp;<strong>syn: </strong>${name_link}`;
                    }
                } else {
                    target.innerHTML = "<strong>Unplaced: </strong>" + name.fullNameStringHtml;
                }

            }

            );
        </script>

        <p class="output" id="example-04">Loading ...</p>

        <hr />

        <h2>5: Filling in a value from lookup.</h2>
        <p>Often we want to populate a form field with a valid WFO ID. This example uses a simple select list to keep
            the
            example as simple as possible. Don't expect it to perform like production code!</p>

        <form>
            <p>Type the first 4+ letters of the name: <input id="example_05_input" type="text"></p>
            <p>Pick name from the list:</p>
            <p>
                <select size="10" id="example-05-select">
                    <option>Search results appear here.</option>
                </select>
            </p>

        </form>


        <script>

            // define the GraphQL query string ahead of times
            let lookup_query =
                `query NameSearch($terms: String!){
                    taxonNameSuggestion(
                        termsString: $terms
                        limit: 100
                    ) {
                        id
                        stableUri
                        fullNameStringPlain,
                        fullNameStringHtml,
                        currentPreferredUsage{
                        hasName{
                            id,
                            stableUri,
                            fullNameStringHtml
                        }
                        }
                    }
                }`;

            // Listen for key up in the text area and do a search
            document.getElementById("example_05_input").onkeyup = function (e) {

                let select = document.getElementById("example-05-select");

                let query_string = e.target.value.trim();
                if (query_string.length > 3) {

                    // tell them we are looking
                    select.innerHTML = "<option>Doing a search ...</option>";

                    // call the api
                    runGraphQuery(lookup_query, { terms: query_string }, (response) => {
                        console.log(response.data);
                        // remove the current children
                        select.childNodes.forEach(child => {
                            select.removeChild(child);
                        });
                        response.data.taxonNameSuggestion.forEach(name => {
                            const opt = document.createElement("option");
                            opt.innerHTML = name.id + ": " + name.fullNameStringHtml;
                            opt.setAttribute('value', name.id);
                            opt.wfo_data = name; // pop the name object on the dom element so we can grab it later
                            select.appendChild(opt);
                        });

                        // if we haven't found anything then put a message in
                        if (select.childNodes.length == 0) {
                            select.innerHTML = `<option>Nothing found for "${query_string}" </option>`;
                        }
                    });


                } else {
                    select.innerHTML = "<option>Add 4 or more letters to search</option>";
                }
            };

            // listen for select change on the select list and render a name if there is one
            document.getElementById("example-05-select").onchange = function (e) {
                const wfo = e.target.value;
                e.target.childNodes.forEach(opt => {
                    if (opt.getAttribute('value') == wfo) {
                        // we've got the chosen name so lets display it like the others 
                        // this is cut and paste code for demo purposes but you get the point.
                        const name = opt.wfo_data;
                        const target = document.getElementById("example-05-display")
                        const name_link = getLinkForName(name); // utility function defined above so we don't have to keep building <a> tags.

                        if (name.currentPreferredUsage) {
                            if (name.currentPreferredUsage.hasName.id == name.id) {
                                target.innerHTML = `<strong>${name_link}</strong>`;
                            } else {
                                let accepted_link = getLinkForName(name.currentPreferredUsage.hasName);
                                target.innerHTML = `<strong>${accepted_link}</strong><br/>&nbsp;&nbsp;&nbsp;<strong>syn: </strong>${name_link}`;
                            }
                        } else {
                            target.innerHTML = "<strong>Unplaced: </strong>" + name.fullNameStringHtml;
                        }
                    }
                });
            }


        </script>

        <p class="output" id="example-05-display">Waiting for pick ...</p>

        <hr />
        <h2>6: Full taxonomic path. Bread crumbs!</h2>

        <p>Showing the full taxonomic path to a name from a WFO ID, in this case from our example synonym:
            wfo-0001048766</p>

        <script>

            // define the GraphQL query string.
            // we can develop this separately using a GraphQL client till we get it right. 
            query =
                `query{
                        taxonNameById(nameId: "wfo-0001048766"){
                            id,
                            stableUri,
                            fullNameStringHtml,
                            currentPreferredUsage{
                                id,
                                hasName{
                                    id,
                                    stableUri,
                                    fullNameStringHtml
                                }
                            }
                        }
                    }`;

            runGraphQuery(query, {}, (response) => {
                let target = document.getElementById("example-06-name")
                let name = response.data.taxonNameById;
                let name_link = getLinkForName(name); // utility function defined above so we don't have to keep building <a> tags.

                if (name.currentPreferredUsage) {

                    // call a recursive function to build parents
                    addAncestor(name.currentPreferredUsage, document.getElementById("example-06-trail"));

                    if (name.currentPreferredUsage.hasName.id == name.id) {
                        target.innerHTML = `<strong>${name_link}</strong>`;
                    } else {
                        let accepted_link = getLinkForName(name.currentPreferredUsage.hasName);
                        target.innerHTML = `<strong>${accepted_link}</strong><br/>&nbsp;&nbsp;&nbsp;<strong>syn: </strong>${name_link}`;
                    }
                } else {
                    target.innerHTML = "<strong>Unplaced: </strong>" + name.fullNameStringHtml;
                }
            });

            function addAncestor(taxon, node) {

                console.log(taxon);
                // here we use a call for the taxon object
                // also not bothering to parameterize the query just write it in
                const query =
                    `query{
                        taxonConceptById(taxonId: "${taxon.id}"){
                            id
                            isPartOf{
                                id,
                                hasName{
                                    id,
                                    stableUri,
                                    fullNameStringHtml
                                }
                            } 
                            }
                        }`;

                        console.log(query);

                runGraphQuery(query, {}, (response) => {
                    const ancestor = response.data.taxonConceptById.isPartOf;

                    // if there is an ancestor to the current taxon then render it
                    if (ancestor) {

                        // if there are already ancestors we need to add a separator
                        if (node.childNodes.length > 0) {
                            node.prepend(document.createTextNode(" > "));
                        }

                        const a = document.createElement("a");
                        a.setAttribute("href", ancestor.hasName.stableUri);
                        a.innerHTML = ancestor.hasName.fullNameStringHtml;
                        node.prepend(a);
                        addAncestor(ancestor, node);
                    }
                });

            }
        </script>

        <div class="output">
            <p id="example-06-trail"></p>
            <hr />
            <p id="example-06-name"></p>
        </div>


        <hr />

        <h2>7: Name and children with synonyms</h2>

        <p>A common thing to want to do is list the subtaxa of a taxon and their synonyms. This is the current treatment
            of the genus <i>Astroloma</i> (wfo-4000003485) </p>

        <script>
            query = `query{
                taxonNameById(nameId: "wfo-4000003485"){
                    id,
                    stableUri,
                    fullNameStringHtml
                    currentPreferredUsage{
                        id,
                        hasPart{
                            id,
                            hasName{
                                id,
                                stableUri,
                                fullNameStringHtml
                            }
                            hasSynonym{
                                id,
                                stableUri,
                                fullNameStringHtml
                            }
                        }
                    }
                }
            }`;

            runGraphQuery(query, {}, (response) => {

                let target = document.getElementById("example-06-name")
                let name = response.data.taxonNameById;

                // just set the root name as a title
                document.getElementById("example-07-name").innerHTML = name.fullNameStringHtml;

                // work through the children
                let kid_list = document.getElementById("example-07-children");
                name.currentPreferredUsage.hasPart.forEach(kid => {

                    // each child is a list item
                    const li = document.createElement("li");
                    li.innerHTML = getLinkForName(kid.hasName);
                    kid_list.append(li);

                    // if we have synonyms we add them
                    if (kid.hasSynonym.length > 0) {
                        // add a UL list of synonyms
                        const syn_list = document.createElement('ul');
                        kid.hasSynonym.forEach(syn => {
                            const li = document.createElement("li");
                            li.innerHTML = getLinkForName(syn);
                            syn_list.append(li);
                        });
                        li.append(syn_list);

                    }

                });

            });

        </script>


        <div class="output">
            <h3 id="example-07-name"></h3>
            <hr />
            <ul id="example-07-children"></ul>
        </div>



        <hr />

        <h2>?: Your suggestion!</h2>
        <p>Please drop me an email if the example you are looking for isn't here or you need some help, <a
                href="mailto:rhyam@rbge.org.uk">Roger Hyam</a></p>


<?php
require_once('footer.php');
?>