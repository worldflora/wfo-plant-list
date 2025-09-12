<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('header.php');


?>
<h1>Ranks</h1>
<p>The WFO Plant List recognises <?php echo count($ranks_table) ?> taxon ranks.

    Each rank has a standard name that is used in the data and software code.
    It has a standard abbreviation that could be used in export. These follows <a
        href="https://www.iapt-taxon.org/nomen/pages/main/art_5.html">Recommendation 5A of the ICNAFP</a> where
    applicable.
    Ranks are often represented with different spellings or abbreviations and these are stored in an "Also Known As"
    field as they are discovered. These variant representations are used during matching and in import scripts.
    Finally ranks are used to control where a name can be placed in the taxonomy. Each rank has a list of the permitted
    ranks that its direct child taxa may have. We expect to grow the AKA list but not to add new ranks.


</p>

<table class="table table-hover">
    <tr>
        <th>Level</th>
        <th>Name (internal)</th>
        <th>Abbreviation (standard)</th>
        <th>Plural</th>
        <th>Also Known As</th>
        <th>Permitted Children</th>
    </tr>
    <?php

$level = 0;

foreach($ranks_table as $rank_name => $rank){
    echo "<tr>";
    
    // level
    echo "<td>$level</td>";
    $level++;
    
    // internal name
    echo "<td>$rank_name</td>";

    // standard abbreviation
    echo "<td>{$rank['abbreviation']}</td>";

    // plural version
    echo "<td>{$rank['plural']}</td>";

    // Also Known As
    $aka = implode('; ', $rank['aka']);
    echo "<td>$aka</td>";

    $children = implode('; ', $rank['children']);
    echo "<td>$children</td>";

    echo "<tr>";
}

?>
</table>


<?php

require_once('footer.php');

?>