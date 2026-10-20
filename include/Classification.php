<?php

/**
 * This is a wrapper around a SOLR doc representing taxa and names
 * It loads itself directly from the SOLR index.
 */
class Classification extends PlantList{

 protected static $loaded = array();

    public string $id;
    public string $title;
    public int $taxonCount;
    public int $year;
    public int $month; // as int
    public string $stableUri;

    public function __construct($classification_id, $accepted_taxon_count){

        // add self to the list of created docs
        $this->id = $classification_id;
        $this->taxonCount = $accepted_taxon_count;
        $this->title = "WFO Classification " . $classification_id . " (" .  number_format($this->taxonCount) . " taxa)";
        $parts = explode('-', $this->id);
        $this->year = (int)$parts[0];
        $this->month = (int)$parts[1];

        $this->stableUri = 'https//list.worldfloraonline.org/' . $classification_id;

        // add myself to the list of loaded classifications so I'm not loaded again.
        self::$loaded[$this->id] = $this;

    }

    public static function getById($classification_id){

        // we just load all the classifications the first time we are asked as it is a single query.
        if(!self::$loaded || count(self::$loaded) == 0){

            $query = array(
                'query' => '*:*',
                'facet' => array(
                    'classification_id_s' => array(
                        'type' => "terms",
                        'limit' => -1,
                        'mincount' => 1,
                        'missing' => false,
                        'sort' => 'index',
                        'field' => 'classification_id_s'
                    )
                ),
                'filter' => array(
                    "role_s:accepted" // restrict count to accepted taxa
                ),
                'limit' => 0
            );
            $response = PlantList::getSolrResponse($query);

            //error_log(print_r($response->facets->classification_id_s->buckets, true));

            // get out of here if there are no classifications!
            if(!isset($response->facets->classification_id_s->buckets)){
                error_log('No classifications found!');
                return array();
            }

            foreach ($response->facets->classification_id_s->buckets as $bucket) {
                $c = new Classification($bucket->val, $bucket->count);
            }

            // we always list in desc order
            self::$loaded = array_reverse(self::$loaded);

        }

        if($classification_id == 'ALL'){
           return array_values(self::$loaded);
        }

        if($classification_id == 'DEFAULT'){
            if(array_key_exists(WFO_DEFAULT_VERSION, self::$loaded)){
                return self::$loaded[WFO_DEFAULT_VERSION];
            }else{
                return null;
            }
        }

        if(array_key_exists($classification_id, self::$loaded)){
            return self::$loaded[$classification_id];
        }else{
            return null;
        }

    }


    public function getMonthName($locale = 'en_GB.UTF-8'){
        //return $locale;

        setlocale(LC_TIME, $locale);
        $month_name = strftime("%B", mktime(0, 0, 0, $this->month, 10));
        // back to default locale
        setlocale(LC_TIME, "");
        return $month_name;
    }

    public function getPhyla(){

        $query = array(
            'query' => '*:*',
            'filter' => array(
                "role_s:accepted OR unplaced",
                "classification_id_s:{$this->id}",
                "rank_s:phylum"
            ),
            'fields' => array('id'),
            'limit' => 100
        );
        $docs = PlantList::getSolrDocs($query);

        $phyla = array();

        // error_log(print_r($response->response->docs, true));
        if($docs){
            
            foreach ($docs as $doc) {

                $phyla[] = new TaxonRecord($doc->id);
            }
        }

        return $phyla;

    }

    public function taxonomyLastIndexed(){

        $query = array(
            'query' => '*:*',
            'filter' => array(
                "classification_id_s:{$this->id}"
            ),
            'fields' => array('solr_import_dt'),
            'sort' => 'solr_import_dt DESC',
            'limit' => 1
        );
        $docs = PlantList::getSolrDocs($query);

        // if we don't have anything we return null
        if(count($docs) == 0) return null;

        $t = strtotime($docs[0]->solr_import_dt);

        if(!$t) return null;

        return $t;

    }

    public function contentLastIndexed(){

        $query = array(
            'query' => 'fyllo_last_indexed_dt:[* TO *]',
            'filter' => array(
                "classification_id_s:{$this->id}"
            ),
            'fields' => array('fyllo_last_indexed_dt'),
            'sort' => 'fyllo_last_indexed_dt DESC',
            'limit' => 1
        );
        $docs = PlantList::getSolrDocs($query);

        // if we don't have anything we return null
        if(count($docs) == 0) return null;

        $t = strtotime($docs[0]->fyllo_last_indexed_dt);

        if(!$t) return null;

        return $t;
    }


}