<?php
//Setto l'header per far capire agli user agent che si tratta di una pagina che offre feed RSS in formato ATOM.
header('Content-type: application/atom+xml');

/* 
 * Impostazioni locali in italiano, utilizzato per la stampa di data e ora 
 * (il server deve avere il locale italiano installato
 */
setlocale(LC_TIME, 'it_IT');
?>
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
<?php
/*
 * L'intero tool si basa sulla seguente libreria per comunicare con il database SPARQL
 * Maggiori info sulla libreria qui: http://graphite.ecs.soton.ac.uk/sparqllib/
 */
require_once( "sparqllib.php" );

//Mi collego al database e in caso di errore esco.
$db = sparql_connect( "http://dydra.com/cristianolongo/odhl/sparql" );
if( !$db ) { 
    print $db->errno() . ": " . $db->error(). "\n";
    exit;
}

//Setto i prefissi "event", "locn", "time"
$db->ns( "event","http://purl.org/NET/c4dm/event.owl#" );
$db->ns( "locn","http://www.w3.org/ns/locn#" );
$db->ns( "time","http://www.w3.org/2006/time#" );
$db->ns( "dcterms","http://purl.org/dc/terms/" );

//Imposto ed eseguo la query per estrarre tutti gli eventi, in caso di errore esco
$query = "SELECT ?e ?label ?address ?time ?modified 
	WHERE{
  		?e a event:Event .
		?e rdfs:label ?label .
		?e event:place ?p . 
		?p locn:address ?a . 
		?a locn:fullAddress ?address .
		?e event:time ?timeInterval .
		?timeInterval time:hasBeginning ?begin .
		?begin time:inXSDDateTime ?time .
  	?e dcterms:modified ?modified
	}";
$result = $db->query( $query ); 
if( !$result ) { 
    print $db->errno() . ": " . $db->error(). "\n";
    exit; 
}
$fields = $result->field_array( $result );

//Una funzione che genera un id univoco di un feed o di una entry basato sul link dello stesso e sulla sua data di creazione.
function getIdFromUrl($url) {
   $url = preg_replace('/https?:\/\/|www./', '', $url);
   $id = "tag:" . $url;
   return $id;
}

//Imposto e stampo le informazioni da inserire nei campi del feed
$feedTitle = "Feed eventi opendatahacklab";
$feedSubtitle = "Tutti gli eventi opendatahacklab a portata di feed";
$feedHomePageUrl = "https://opendatahacklab.github.io/";
$feedSelfUrl = "https://opendatahacklab.github.io/rdfevents2rss.php";
$feedUpdatedField = date(DateTime::ATOM);
$feedId = getIdFromUrl($feedSelfUrl, $feedUpdatedField);
$feedIconUrl = "https://opendatahacklab.github.io/imgs/logo_cog4_ter.png";
$feedAuthorName = "Biagio Robert Pappalardo";
$feedAuthorEmail = "vandir92@gmail.com";
?>
<title><?=$feedTitle?></title>
<subtitle><?=$feedSubtitle?></subtitle>
<link href="<?=$feedHomePageUrl?>" />
<link href="<?=$feedSelfUrl?>" rel="self" />
<id><?=$feedId?></id>
<updated><?=$feedUpdatedField?></updated>
<logo><?=$feedIconUrl?></logo>
<author>
<name><?=$feedAuthorName?></name>
<email><?=$feedAuthorEmail?></email>
</author>
<?php
//Imposta e stampa un entry del feed per ciascun evento ottenuto dalla query precedente
while( $row = $result->fetch_array() ) :
	$entryTitle = $row["label"];
	$entryUrl = $row["e"];
    	$entryUpdated = $row['modified'];
	$entryId = getIdFromUrl($entryUrl, $entryUpdated);
	$entrySummary = $entryTitle . " - " . 
			"Indirizzo: " . $row['address'] . " - " .
			"Data: " . strftime("%A %d %B %Y alle ore %H:%M" , strtotime($row['time']));
?>
<entry>
<title><?=$entryTitle?></title>
<link rel="alternate" type="text/html" href="<?=$entryUrl?>"/>
<id><?=$entryId?></id>
<updated><?=$entryUpdated?></updated>
<summary><?=$entrySummary?></summary>
</entry>
<?php endwhile; ?>
</feed>
