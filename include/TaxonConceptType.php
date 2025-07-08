<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\EnumType;

require_once('../include/TypeRegister.php');

class TaxonConceptType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => "A TaxonConcept is an accepted group of organisms within a classification( a.k.a taxonomy).
                A TaxonConcept has a link to one official TaxonName and potentially multiple synonymous TaxonNames.
                TaxonConcepts also have set type relationships to other TaxonConcepts in the classification hierarchy and version type relationships with TaxonConcepts in other classifications.",
            'fields' => function(){
                return [
                    'id' => [
                        'type' => Type::string(),
                        'description' => "A locally identifier for this TaxonConcept (actually the WFO ID with year qualifier)",
                        'resolve'=>function($record, $args, $context, $info) {return $record->getId();}
                    ],
                    'title' => [
                        'type' => Type::string(),
                        'description' => 'Needed by some GraphQL libraries this is a string rendering of he object probably only useful in development',
                        'resolve'=>function($record, $args, $context, $info) {return $record->getTitle();}
                    ],
                    'stableUri' => [
                        'type' => Type::string(),
                        'description' => "A URI to the human readable web page for this resource.",
                        'resolve'=>function($record, $args, $context, $info) {return $record->getStableUri();}
                    ],
                    'classificationId' => [
                        'type' => Type::string(),
                        'description' => "The ID of the classification this taxon belongs to e.g. 2022-12.",
                        'resolve'=>function($record, $args, $context, $info) {return $record->getClassificationId();}
                    ],
                    'classification' => [
                        'type' => TypeRegister::classificationType(),
                        'description' => "The classification object based on the classificationId",
                        'resolve'=>function($record, $args, $context, $info) {return $record->getClassification();}
                    ],
                    'comment' => [
                        'type' => Type::string(),
                        'description' => "Notes and comments about this taxon",
                        'resolve'=>function($record, $args, $context, $info) {return $record->getComment();}
                    ],

                    'references' => [
                        'type' => Type::listOf(TypeRegister::referenceType()),
                        'description' => "References to other resources on the internet.",
                        'resolve' => function($record){return $record->getTaxonomicReferences();}
                    ],

                    'hasName' => [
                        'type' => TypeRegister::taxonNameType(),
                        'resolve' => function($record){ 
                            return $record->getName(); 
                        }, // yes the same object just in another wrapper!
                        'description' => "The name that should be used for this taxon according to the International Code of Botanical Nomenclature. i.e. the accepted name for the TaxonConcept. Synonyms are accessed by hasSynonym."
                    ],
                    'hasSynonym' => [
                        'type' => Type::listOf(TypeRegister::taxonNameType()),
                        'resolve' => function($taxon){return $taxon->getSynonyms(); },
                        'description' => "A name associated with this TaxonConcept which should not be used.
                        This includes homotypic (nomenclatural) synonyms which share the same type specimen as the accepted name 
                        and heterotypic (taxonomic) synonyms whose type specimens are considered to fall within the circumscription of this taxon."
                    ],
                    'hasUnplacedName' => [
                        'type' => Type::listOf(TypeRegister::taxonNameType()),
                        'args' => [
                            'offset' => [
                                'type' => Type::int(),
                                'description' => 'How far through the result set to start.',
                                'required' => false,
                                'default' => 0
                            ],
                            'limit' => [
                                'type' => Type::int(),
                                'description' => 'Maximum number of results to return',
                                'required' => false,
                                'default' => 1000
                            ]
                        ],
                        'resolve' => function($taxon, $args, $context, $info){
                            $limit = isset($args['limit']) ? $args['limit'] : 1000;
                            $offset = isset($args['offset']) ? $args['offset'] : 0;
                            return $taxon->getUnplacedNames($limit, $offset);
                        },
                        'description' => "Names with this genus name that haven't been placed in the taxonomy yet. Only applicable to genera. Returns null for other ranks."
                    ],
                    'isPartOf' => [
                        'type' => TypeRegister::taxonConceptType(),
                        'resolve' => function($record){return $record->getParent();},
                        'description' => "The parent taxon of the current taxon within this classification"
                    ],
                    'path' => [
                        'type' => Type::listOf(TypeRegister::taxonConceptType()),
                        'resolve' => function($record){return $record->getPath();},
                        'description' => "The path of inclusion from the root taxon to this taxon. Good for bread crumb trails."
                    ],
                    'pathString' => [
                        'type' => Type::string(),
                        'resolve' => function($record){return $record->getWfoPath();},
                        'description' => "The path of inclusion as a string of taxon names, kinda like a file path"
                    ],
                    'hasPart' => [
                        'type' => Type::listOf(TypeRegister::taxonConceptType()),
                        'resolve' => function($record, $args, $context, $info){
                            // we load the name if we need to!

                            $limit = -1;
                            if(isset($args['limit'])) $limit = $args['limit'];

                            $offset = 0;
                            if(isset($args['offset'])) $offset = $args['offset'];

                            return $record->getChildren($limit, $offset);

                        },
                        'args' => [
                            'offset' => [
                                'type' => Type::int(),
                                'description' => 'How far through the result set to start.'
                            ],
                            'limit' => [
                                'type' => Type::int(),
                                'description' => 'Maximum number of results to return'
                            ]
                        ],
                        'description' => "A sub taxon of the current taxon within this classification"
                    ],
                    'partsCount' => [
                        'type' => Type::int(),
                        'description' => "The number of subtaxa (parts) of this taxon.",
                        'resolve' => function($record){return $record->getChildCount();}
                    ],
                    'replaces' => [
                        'type' => TypeRegister::taxonConceptType(),
                        'resolve' => function($record){return $record->getReplaces();},
                        'description' => "The nearest equivalent taxon in the previously published classification. See also notes under isReplacedBy"
                    ],
                    'isReplacedBy' => [
                        'type' => TypeRegister::taxonConceptType(),
                        'resolve' => function($taxon){return $taxon->getReplacedBy();},
                        'description' => "The nearest equivalent taxon in the next published classification."
                    ],
                    'stats' => [
                        'type' => Type::listOf(TypeRegister::taxonConceptStatType()),
                        'resolve' => function($taxon){
                            return $taxon->getStats();
                        },
                        'description' => "A selection of statistics that can be gleaned from the index."
                    ]

                ];
            }
        ];
        parent::__construct($config);

    }



}