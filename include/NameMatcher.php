<?php

/**
 * This is a wrapper around a SOLR doc representing taxa and names
 * It loads itself directly from the SOLR index.
 */
class NameMatcher extends PlantList{

    private $params;

    /**
     * Create a matcher with configured behaviour
     * for matching using the match() method.
     * 
     * @param Object $config_params Configuration for the matcher as an array
     */
    public function __construct($config_params){
        
        $this->params = $config_params;

        // override with some defaults if they haven't been set
        if(!isset($this->params->method)) $this->params->method = 'alpha';
        //if(!isset($this->params->includeDeprecated)) $this->params->includeDeprecated = false;
        if(!isset($this->params->limit)) $this->params->limit = 50;
        if(!isset($this->params->classificationVersion)) $this->params->classificationVersion = WFO_DEFAULT_VERSION;
        if(!isset($this->params->checkHomonyms)) $this->params->checkHomonyms = false;
        if(!isset($this->params->checkRank)) $this->params->checkRank = false;
        if(!isset($this->params->acceptSingleCandidate)) $this->params->acceptSingleCandidate = false;

        // fuzzy duck
        if(!isset($this->params->fuzzyNameParts)) $this->params->fuzzyNameParts = 0;
        if(!isset($this->params->fuzzyAuthors)) $this->params->fuzzyAuthors = 0;

        // restrict by year - added for TETTRIs
        if(!isset($this->params->year)) $this->params->year = null;
        
        // restrict by rank - added for TETTRIs
        if(!isset($this->params->rank)) $this->params->rank = null;

        // restrict by authors string - added for TETTRIs
        if(!isset($this->params->authors)) $this->params->authors = null;
        
    }

    /**
     * Called on a configured NameMatcher
     */
    public function match($inputString){

        $response = new class{};
        $response->inputString = $inputString; // raw string submitted
        $response->searchString = trim($inputString);  // sanitized string we actually search on
        $response->params = $this->params;
        $response->match = null;
        $response->candidates = array();
        $response->error = false;
        $response->errorMessage = null;

        // we do some common sanitizing at this level
        
        // hybrid symbols be gone
        /*
        $json = '["\u00D7","\u2715","\u2A09"]';
        $hybrid_symbols = json_decode($json);
        foreach ($hybrid_symbols as $symbol) {
            $response->searchString = trim(str_replace($symbol, '', $response->searchString));
        }
        */

        switch ($this->params->method) {
            case 'alpha':
                return $this->alphaMatch($response);
                break;
            case 'full':
                return $this->fullMatch($response);
                break;
            default:
                throw new ErrorException("Unrecognized matching method {$this->params->method}");
                break;
        }
    }


    /**
     * 
     * A comprehensive search.
     * 
     */
    private function fullMatch($response){

        global $ranks_table;

        $response->parsedName = new class{};
        $response->narrative = array();

        /*
            - possible name structures.
            Word
            Word author-string
            Word word
            Word word author-string
            Word rank word author-string
            Word word word author-string
            Word word rank word author-string
        */

        // lets parse the name out
        $parts = explode(" ", ucfirst($response->searchString));

        // there should be no parts that are just punctuation
        $parts = preg_grep('/[a-zA-Z&]+/', $parts);

        // nothing starting with a - as it will break solr
        $parts = preg_grep('/^-/', $parts, PREG_GREP_INVERT);

        // now the numbers in the array may be off
        $parts = array_values($parts);        

        $canonical_parts = array(); // this is just the name parts - up to 3 words
        $response->parsedName->rank = null; // if we can find one
        $authors = null;

        // the first word is always a taxon word
        $canonical_parts[] =  $this->sanitizeNameString($parts[0]);
        $response->narrative[] = "The first word is always a name part word: '{$canonical_parts[0]}'";
        $final_word_part = 0;

        // look for subsequent parts
        $autonym_rank = false;
        $had_lower_case_word = false; // and we are therefore likely below species level
        for($i = 1; $i < count($parts); $i++){

            // if we only have one part don't bother
            if(!isset($parts[$i])) break;

            $word = $parts[$i];

            if(!$word) continue;

            // is this a rank?
            if(PlantList::isRankWord($word)){

                // we have found the rank
                $response->parsedName->rank = PlantList::isRankWord($word);
                $response->narrative[] = "Rank estimated as '{$response->parsedName->rank}' based on '$word'.";

                // the following word is alway a name part and completes the name
                if($i+1 < count($parts)){
                    $canonical_parts[] = $parts[$i+1];
                    $final_word_part = $i+1;
                    $response->narrative[] = "Word following rank is always name part: '{$parts[$i+1]}'.";
                }
                break;

            }

            $is_name_word = true;

            // see if it is anything other than just letters
            // or if it starts with a capital when we have already had a lowercase name word (handles case of authors with names that are also genus names.)
            //  - and therefore not a word-part but an author string
            if(preg_match('/[^a-zA-Z\-]/', $word)){
                $is_name_word = false;
                $response->narrative[] = "Word contains non alpha chars and so is start of author string: '$word'.";
            }elseif($had_lower_case_word && preg_match('/^[A-Z]/', $word)){
                $is_name_word = false;
                $response->narrative[] = "Word starts with a capital when we have already had a name word starting with a lower case so part of author string: '$word'.";
            }else{
                    // Is the word a recognized name? 

                    $word = $this->sanitizeNameString($word); // we can strip accents now

                    $query = array(
                        'query' => 'name_string_s:' . $word, 
                        'limit' => 0
                    );
                    $solr_response = PlantList::getSolrResponse($query);
                    if(isset($solr_response->response->numFound)){
                        if($solr_response->response->numFound > 0){
                            $is_name_word = true;
                            $response->narrative[] = "Word is found in index and so is part of name: '$word'.";
                            // if it is a lowercase word we remember that as subsequent name words should not start
                            // with capitals
                            if(preg_match('/^[a-z]/', $word)){
                                $response->narrative[] = "Word is lowercase. Subsequent words must start with lowercase: '$word'.";
                                $had_lower_case_word = true; 
                            } 
                        }else{
                            if($i == 1 && preg_match('/^[a-z]+/', $word)){
                                $is_name_word = true;
                                $response->narrative[] = "Word is NOT found in index BUT is second and has lowercase first letter so most probably novel/erroneous epithet: '$word'.";
                            }else{
                                $is_name_word = false;
                                $response->narrative[] = "Word is NOT found in index and so start of authors: '$word'.";
                            }
                        }
                    }else{
                        echo "<p>SOLR Issues</p>";
                        echo "<pre>";
                        print_r($solr_response);
                        echo "<pre/>";
                        exit;
                    }
            }

            // is this word a name part or something else?
            if($is_name_word){                
                // we are building the canonical name OK
                $canonical_parts[] = $word;
                $final_word_part = $i;
            }else{

                // we have run into the author string start
                $final_word_part = $i-1; // the last word was the final word part (ignoring autonym parts )

                // This might be an autonym with authors between the species part and the subspecific part
                if(count($canonical_parts) == 2){
                    $response->narrative[] = "There are two name parts. This may be an autonym with species authors included. Checking for second occurrence of '{$canonical_parts[1]}'";
                    for($j = $i; $j < count($parts); $j++){

                        if(!isset($parts[$j])) break; // don't try and go beyond bounds

                        if($parts[$j] == $canonical_parts[1]){
                            $response->narrative[] = "Found second '{$canonical_parts[1]}'. This is an autonym with authors.";
                            $canonical_parts[] = $canonical_parts[1];
                            break;
                        }
                        
                    }

                    // check for the rank in the authors string of autonym
                    if(count($canonical_parts) == 3 && $canonical_parts[2] == $canonical_parts[1]){
                        for($j = $i; $j < count($parts); $j++){
                            if(PlantList::isRankWord($parts[$j])){
                                $response->parsedName->rank = PlantList::isRankWord($parts[$j]);
                                $response->narrative[] = "Rank estimated as '{$response->parsedName->rank}' based on '{$parts[$j]}'.";
                                $autonym_rank = $parts[$j];
                                break;
                            }
                        }
                    }

                }
                
                break; // stop adding words we have done the authors part
            }

            // if we have found 3 name-parts we should definitely stop
            if(count($canonical_parts) == 3){
                $response->narrative[] = "Found three name words and so rest of string must be authors.";
                break;
            }

        }

        // We have the canonical word parts
        // if we are in fuzzy mode then we replace where necessary
        if($this->params->fuzzyNameParts > 0){

            // work through each of the name parts and see if they
            // occur appropriately in the index. If they don't then
            // replace with something appropriate
            for ($i=0; $i < count($canonical_parts); $i++) { 
                $part = $canonical_parts[$i];

                // look it up in the index
                $query = array(
                    'query' => 'name_string_s:' . $part, 
                    'limit' => 10
                );
                $solr_response = PlantList::getSolrResponse($query);

                if(isset($solr_response->response->numFound) && $solr_response->response->numFound > 0){
                    $response->narrative[] = "'{$part}' is found in the index and therefore most likely a good name part.";
                }else{
                    $response->narrative[] = "'{$part}' is NOT found in the index and therefore most likely NOT a good name part.";
                    $response->narrative[] = "Fuzzy searching for single candidate replacement.";

                    $filters = array();
                    $filters[] = 'classification_id_s:' . $this->params->classificationVersion;
                    
                    if($i == 0){
                        // the first word can't be an epithet 
                        $filters[] = '-genus_string_s:["" TO *]';
                    }else{
                        // subsequent words will always have at least genus part.
                        $filters[] = 'genus_string_s:*';
                    }

                    $query = array(
                        'query' => 'name_string_s:' . $part . '~' . $this->params->fuzzyNameParts, 
                        'filter' => $filters,
                        'limit' => 10
                    );   

                    $solr_response = PlantList::getSolrResponse($query);
                    if(isset($solr_response->response->numFound) && $solr_response->response->numFound > 0){
                        // check that we only have one word - not multiple variations
                        $candidates = array();
                        foreach($solr_response->response->docs as $doc){
                            $candidates[] = $doc->name_string_s;
                        }
                        $candidates = array_unique($candidates);
                        if(count($candidates) == 1){
                            $canonical_parts[$i] = $solr_response->response->docs[0]->name_string_s;
                            $response->narrative[] = "Found a single candidate replacement, '{$canonical_parts[$i]}', so swapping to it.";
                        }else{
                            $response->narrative[] = "Found multiple candidate replacements you might want to check: " . implode('; ', $candidates)  . '.';
                        }

                    }else{
                        $response->narrative[] = "Not found a single candidate replacement.";
                    }
                }

                
            } // for each part

        } // if fuzzy words

        // build the name out of the max three canonical parts
        $response->parsedName->canonical_form = implode(' ', $canonical_parts);

        // all the rest of the parts are the authors string
        $response->parsedName->author_string = trim(implode(' ', array_slice($parts, $final_word_part +1)));
       
        if($response->parsedName->author_string){
            $response->narrative[] = "Authors string looks like this: '{$response->parsedName->author_string}'";
        }else{
            $response->narrative[] = "No authors string provided. Will match all authors strings.";
        }

        // If we are dealing with an autonym the name may be embedded in the author string when we take this approach.
        if(count($canonical_parts) == 3 && $canonical_parts[1] == $canonical_parts[2] && strpos($response->parsedName->author_string, $canonical_parts[1]) !== false){
            $response->parsedName->author_string = trim(str_replace("{$canonical_parts[1]}", " ", $response->parsedName->author_string));
            $response->narrative[] = "Autonym so removed name part from authors: '{$response->parsedName->author_string}'";

            if($autonym_rank){
                $response->parsedName->author_string = trim(str_replace("{$autonym_rank}", " ", $response->parsedName->author_string));
                $response->narrative[] = "Autonym so removed rank '$autonym_rank' from authors: '{$response->parsedName->author_string}'";
            }

        }

        $response->narrative[] = "Parsed name complete.";

        // we can assume it is a species if there are two words and the second is lower case
        if(!$response->parsedName->rank && count($canonical_parts) == 2 && preg_match('/^[a-z]+/', $canonical_parts[1])  ){
            $response->parsedName->rank = 'species';
            $response->narrative[] = "Rank estimated as 'species' from name parts.";
        }

        // build a filter for the main search if they have passed in 
        $filters = array();

        // we always filter to the default classification
        $filters[] = 'classification_id_s:' . WFO_DEFAULT_VERSION; 

        // is the year filtered
        if($this->params->year){
            $response->narrative[] = "Filter to names only from year {$this->params->year} added.";
            $filters[] = 'publication_year_i:' . $this->params->year; 
        }

        // is the rank filtered
        if($this->params->rank){
            $response->narrative[] = "Filter to names only in rank {$this->params->rank} added.";
            $filters[] = 'rank_s:' . $this->params->rank; 
        }

        // is the authors string is filtered
        if($this->params->authors){
            $response->narrative[] = "Filter to names only in with an author string containing '{$this->params->authors}' added.";
            $filters[] = 'authors_string_s:"' . trim($this->params->authors) . '"'; 
        }

        // LET'S ACTUALLY DO THE SEARCH NOW!
        // get everything with a matching canonical name
        $query = array(
            'query' => 'full_name_string_alpha_s:"' . $response->parsedName->canonical_form . '"',
            'filter' => $filters,
            'limit' => 100
        );
        $docs = PlantList::getSolrDocs($query);

        $doc_count = $docs ? count($docs) : 0; // docs may be null

        $response->narrative[] = "Searched index of " . WFO_DEFAULT_VERSION ." for canonical form of name '{$response->parsedName->canonical_form}' and found " . $doc_count . " candidates.";
        
        // rather than do convoluted logic we do it step wise.

        // they are all candidates
        if($docs){
            foreach($docs as $doc){
                $doc->asName = true;
                $response->candidates[] = new TaxonRecord($doc);
            }
        }

        // do we have a single one with a good author string?
        if($this->params->checkHomonyms){
            $response->narrative[] = "Check all homonyms is selected so not checking author strings.";
        }else{

            // checking author strings only if we have some candidates.
            if($response->candidates){

                    $response->narrative[] = "Looking for matching author strings.";

                    foreach($response->candidates as $candidate){

                        // are we in fuzzy author land?
                        if($this->params->fuzzyAuthors > 0){
                            $lev = levenshtein($candidate->getAuthorsString(), $response->parsedName->author_string);
                            $authors_match = $lev <=  $this->params->fuzzyAuthors;
                            if ($authors_match){
                                $response->narrative[] = "Fuzzy author match '{$candidate->getAuthorsString()}' with Levenshtein distance $lev";
                            }else{
                                $response->narrative[] = "Fuzzy author do NOT match '{$candidate->getAuthorsString()}'. Levenshtein distance $lev";
                            }
                        }else{
                            $authors_match = $candidate->getAuthorsString() == $response->parsedName->author_string;
                        }

                        if($response->parsedName->author_string && $authors_match){
            
                            if($response->match && $response->match != $candidate){
                                // we have found a second with good author strings!
                                if($response->match->getRole() == 'deprecated' && $candidate->getRole() != 'deprecated'){
                                    // a good name over rules a deprecated one
                                    $response->match = $candidate;
                                    $response->narrative[] = "Deprecated name removed to reveal matched name.";
                                }else{
                                    // we have two and one isn't deprecated so we can't decide
                                    // between them
                                    $response->match = null;
                                    $response->narrative[] = "No candidate has matching author string.";
                                    break;
                                }
                            }else{
                                // this become the new match
                                $response->match = $candidate;
                                $response->narrative[] = "Found candidate ({$candidate->getWfoId()}) with matching author string so it becomes the match.";
                            }
                        }
                    }

                    // if the search string has an ex in it then look without the ex author
                    // second author is real one.
                    if(!$response->match && $response->parsedName->author_string){
                        if(strpos($response->parsedName->author_string, ' ex ') !== false){
                            $response->narrative[] = "Submitted authors contain ' ex '. Removing the ex and checking authors again.";
                            $ex_less_authors = $this->removeExAuthors($response->parsedName->author_string);
                            foreach($response->candidates as $candidate){
                                if($candidate->getAuthorsString() == $ex_less_authors){
                                    $response->match = $candidate;
                                    $response->narrative[] = "Found matching authors when ex removed from submitted name.";
                                    break;
                                }
                            }
                        }else{
                            $response->narrative[] = "Submitted authors do NOT contain ' ex '. Looking for match in candidates if their ex authors are removed.";
                            foreach($response->candidates as $candidate){
                                $ex_less_authors = $this->removeExAuthors($candidate->getAuthorsString());
                                if($response->parsedName->author_string == $ex_less_authors){
                                    $response->match = $candidate;
                                    $response->narrative[] = "Found matching authors when ex removed from candidate name.";
                                    break;
                                }
                            }
                        }
                    }

             

            } // if we have candidates we check author strings

        } // checking author strings 

        // if we have a single candidate and the input name doesn't have 
        // an authorstring then we assume that it is a match 
        if(count($response->candidates) == 1 && strlen($response->parsedName->author_string) == 0){
            $response->match = $response->candidates[0];
            $response->narrative[] = "Only one candidate found ({$response->match->getWfoId()}) and no author string supplied so name becomes match.";
        }

        // if we find a single candidate and the search term is an autonym and the match is an autonym and it is the same rank
        // then we match it
        if(
            count($response->candidates) == 1 && count($canonical_parts) == 3 && $canonical_parts[1] == $canonical_parts[2] // search is autonym
            && $response->candidates[0]->getNameString() == $response->candidates[0]->getSpeciesString() // match is autonym // match and search ranks are the same
        ){
            $response->narrative[] = "Only one candidate found ({$response->candidates[0]->getWfoId()}) it is an autonym and so is the supplied name.";
            if($response->candidates[0]->getRank() == $response->parsedName->rank){
                $response->narrative[] = "The ranks are the same so making it a match regardless of any author string.";
                $response->match = $response->candidates[0];
            }else{
                $response->narrative[] = "The ranks are not the same ('{$response->candidates[0]->getRank()}' and '{$response->parsedName->rank}') so not a match.";
            }

        }

        // they care about ranks so remove the match if the ranks don't match
        if($response->match && $this->params->checkRank && $response->parsedName->rank && $response->parsedName->rank != $response->match->getRank()){
            // they want the ranks to match and they don't so demote it
            $response->match = null;
            $response->narrative[] = "Checked ranks and they didn't match.";
        }

        // if we haven't found anything but they would be happy with a genus
        if(!$response->match && @$this->params->fallbackToGenus && count($canonical_parts) > 0){
            
            $response->narrative[] = "No match was found but fallbackToGenus is true so looking for genus.";
            
            $filters = array();
            $filters[] = 'classification_id_s:' . $this->params->classificationVersion;
            $filters[] = 'rank_s:genus';
            $filters[] = '-role_s:deprecated';
            
            $query = array(
                'query' => "name_string_s:" . $canonical_parts[0],
                'filter' => $filters,
                'limit' => $this->params->limit
            );

            $docs = PlantList::getSolrDocs($query);
            $response->candidates = array(); // scrub existing candidates
            if($docs){
                foreach($docs as $doc){
                    $doc->asName = true;
                    $response->candidates[] = new TaxonRecord($doc);
                }
            }


            // do we only have one?
            if(count($response->candidates) == 1){
                $response->narrative[] = "A single genus candidate found so it becomes the match.";
                $response->match = $response->candidates[0];
                $response->candidates = array();
            }else{
                $response->narrative[] = count($response->candidates) . " genus candidates found so no match.";
            }

        }

        // Have we got a match?
        if(!$response->match){
            
            $response->narrative[] = "Still no match found.";

            // have we got any canditates
            if(count($response->candidates) > 0){

                $response->narrative[] = "But " . count($response->candidates) . " candidates found.";

                if(count($response->candidates) == 1 && @$this->params->acceptSingleCandidate){
                    // a single candidate and that will do for them!
                    $response->narrative[] = "A single candidate found and acceptSingleCandidate is true so it becomes the match.";
                    $response->match = $response->candidates[0];
                    $response->candidates = array();
                }

            }else{

                if($this->params->year || $this->params->rank || $this->params->authors){

                    $response->narrative[] = "No candidates found and filter in place not attempting enhanced search.";

                }else{

                        // they haven't restricted by a filter so we go wild and try and find a match some other way. 

                        $response->narrative[] = "No candidates found so moving to enhanced search (single candidates not accepted as matches)";

                        $canonical_name = $response->parsedName->canonical_form;
                        $canonical_name = str_replace(' ', '\ ', $canonical_name);
                        $already_in_results = array();

                        while(strlen($canonical_name) > 3){

                            $filters = array();
                            $filters[] = 'classification_id_s:' . $this->params->classificationVersion;

                            $query = array(
                                'query' => "full_name_string_alpha_s:{$canonical_name}*",
                                'filter' => $filters,
                                'sort' => 'full_name_string_alpha_t_sort asc',
                                'limit' => $this->params->limit
                            );
                        
                            $docs  = $this->getSolrDocs($query);
                
                            if($docs){

                                $n = str_replace('\\', '', $canonical_name);
                                $c = count($docs);

                                $response->narrative[] = "Found $c names for '{$n}'";


                                foreach ($docs as $doc) {
                                    $doc->asName = true;
                                    if(count($response->candidates) < $this->params->limit && !in_array($doc->wfo_id_s, $already_in_results)){
                                        $response->candidates[] = new TaxonRecord($doc);
                                        $already_in_results[] = $doc->wfo_id_s;
                                    }
                                }
                            
                                if(count($response->candidates) >= $this->params->limit){
                                    // go round again with a slightly shorter name
                                    $response->narrative[] = "Got  {$this->params->limit} candidates so stopping search.";
                                    break;
                                }

                            }else{
                                $n = str_replace('\\', '', $canonical_name);
                                $response->narrative[] = "Found no names for '{$n}'";
                            }
                            $canonical_name = substr($canonical_name, 0, -1);
                        
                        } // while still got > 3 letters

                        // if we only have a single candidate --- 
                        if(count($response->candidates) == 1 && @$this->params->acceptSingleCandidate){
                            // a single candidate and that will do for them!
                            $response->narrative[] = "A single candidate found and acceptSingleCandidate is true so it becomes the match.";
                            $response->match = $response->candidates[0];
                            $response->candidates = array();
                        }

                }// no filters set &  no candidates so go relevance
    

            } //no candidates

        } // not got a match

        // before returning we check that if we are returning a 
        // match we do not also return candidates
        if($response->match) $response->candidates = array();

        return $response;
    }

    private function removeExAuthors($authors){

        // no ex then just return them
        if(strpos($authors, ' ex ') === false) return $authors;

        // the ex may be in the parenthetical authors
        if(preg_match('/\(.+ ex .+\)/', $authors)){
            return preg_replace('/[^(]+ ex /', '', $authors);
        }else{
            return preg_replace('/^.+ ex /', '', $authors);
        }

    }

    /**
     * A simple alphabetical lookup of names
     * 
     */
    private function alphaMatch($response){

        // we only do it if we have more than 3 characters?
        if(strlen($response->searchString) < 4){
            $response->error = true;
            $response->errorMessage = "Search string must be more than 3 characters long.";
            return $response;
        }

        $name = trim($response->searchString);
        $name = ucfirst($name); // all names start with an upper case letter
        $name = str_replace(' ', '\ ', $name);
        $name = $name . "*";

        $filters = array();
        $filters[] = 'classification_id_s:' . $this->params->classificationVersion;
        if(isset($this->params->excludeDeprecated) && $this->params->excludeDeprecated){
            $filters[] = '-role_s:deprecated'; 
        }

        $query = array(
            'query' => "full_name_string_alpha_s:$name",
            'filter' => $filters,
            'sort' => 'full_name_string_alpha_t_sort asc',
            'limit' => $this->params->limit
        );

        $docs  = $this->getSolrDocs($query);

        if($docs){
            if(count($docs) == 1){
                $docs[0]->asName = true;
                $response->match = new TaxonRecord($docs[0]);
            }else{
                foreach ($docs as $doc) {
                    $doc->asName = true;
                    $response->candidates[] = new TaxonRecord($doc);
                }
            }
        }


        return $response;
    }


        /**
     * Remove and swap any dodgy characters
     * 
     */
    public function sanitizeNameString($dirty){

        $cleaner = trim($dirty);

        /*

            60.7.  Diacritical signs are not used in scientific names. When names
            (either new or old) are drawn from words in which such signs appear, the
            signs are to be suppressed with the necessary transcription of the letters so
            modified; for example ä, ö, ü become, respectively, ae, oe, ue (not æ or œ,
            see below); é, è, ê become e; ñ becomes n; ø becomes oe (not œ); å becomes
            ao.

        */

        $cleaner = str_replace('ä', 'ae', $cleaner);
        $cleaner = str_replace('ö', 'oe', $cleaner);
        $cleaner = str_replace('ü', 'ue', $cleaner);
        $cleaner = str_replace('é', 'e', $cleaner);
        $cleaner = str_replace('è', 'e', $cleaner);
        $cleaner = str_replace('ê', 'e', $cleaner);
        $cleaner = str_replace('ë', 'e', $cleaner);
        $cleaner = str_replace('ñ', 'n', $cleaner);
        $cleaner = str_replace('ø', 'oe', $cleaner);
        $cleaner = str_replace('å', 'ao', $cleaner);
        $cleaner = str_replace("'", '', $cleaner); // can you believe an o'donolli 

        // we don't do hybrid symbols or other weirdness
        $cleaner = str_replace(' X ', '', $cleaner); // may use big X for hybrid  - we ignore
        $cleaner = str_replace(' x ', '', $cleaner); // may use big X for hybrid - we ignore
        //$cleaner = preg_replace('/[^A-Za-z\-. ]/', '', $cleaner); // any non-alpha character, hyphen or full stop (OK in abbreviated ranks) 
        $cleaner = preg_replace('/\s\s+/', ' ', $cleaner); // double spaces

        return $cleaner;

    }

}