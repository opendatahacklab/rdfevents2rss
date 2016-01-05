<?php
/**
 * Copyright 2016 Biagio Robert Pappalardo and Cristiano Longo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

//Setto l'header per far capire agli user agent che si tratta di una pagina che offre feed RSS in formato ATOM.
header('Content-type: application/atom+xml; charset=UTF-8');

/* 
 * Impostazioni locali in italiano, utilizzato per la stampa di data e ora 
 * (il server deve avere il locale italiano installato
 */
setlocale(LC_TIME, 'it_IT');

/*
 * L'intero tool si basa sulla seguente libreria per comunicare con il database SPARQL
 * Maggiori info sulla libreria qui: http://graphite.ecs.soton.ac.uk/sparqllib/
 */
require_once( "sparqllib.php" );
require_once( "AtomFeedGenerator.php" );

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
	}
	ORDER BY DESC(?modified)";
$result = $db->query( $query );  
if( !$result ) { 
    print $db->errno() . ": " . $db->error(). "\n";
    exit; 
}
$result->field_array( $result );

//Una funzione che genera un id univoco di un feed o di una entry basato sul link dello stesso e sulla sua data di creazione.
function getIdFromUrl($url, $dateAtomFormat) {
   $date = date("Y-m-d", strtotime($dateAtomFormat));
   $url = preg_replace('/https?:\/\/|www./', '', $url);
   if ( strpos($url, '/') !== false) {
      $ex = explode('/', $url);
      $urlpredomain = $ex['0'];
   }
   $urlpostdomain = str_replace($urlpredomain, "", $url);
   $id = "tag:" . $urlpredomain . "," . $date . ":" . $urlpostdomain;
   return $id;
}

/* 
 * Cerco il campo "modified" più alto (che corrisponderà al campo <updated> dell'ultima entry che si inserirà) 
 * per impostarlo come campo <updated> del feed. Esso è il primo perchè i risultati sono ordinati in base a questo campo.
 */
$maxTimestamp = 0;
$row = $result->fetch_array();
$maxTimestamp = $row['modified'];


//Imposto e stampo le informazioni da inserire nei campi del feed
$feedTitle = "Eventi opendatahacklab";
$feedSubtitle = "Tutti gli eventi opendatahacklab a portata di feed";
$feedHomePageUrl = "https://opendatahacklab.github.io/";
$feedSelfUrl = "https://opendatahacklab.github.io/rdfevents2rss.php";
$feedUpdatedField = $maxTimestamp;
$feedId = getIdFromUrl($feedSelfUrl, $feedUpdatedField);
$feedIconUrl = "https://opendatahacklab.github.io/imgs/logo_cog4_ter.png";
$feedAuthorName = "Biagio Robert Pappalardo";
$feedAuthorEmail = "vandir92@gmail.com";

//TODO updated
$feed=new AtomFeedGenerator($feedId, $feedTitle, new DateTime($maxTimestamp),
		$feedSelfUrl, $feedAuthorName, null, $feedAuthorEmail);
$feed->addFeedAuthor("Cristiano Longo", null, "longo@dmi.unict.it");
/* <link href="<?=$feedHomePageUrl?>" /> */
/* <logo><?=$feedIconUrl?></logo> */

//Imposta e stampa un entry del feed per ciascun evento ottenuto dalla query precedente
// Usa un ciclo while perchè il primo "$row = $result->fetch_array()" è stato chiamato sopra
// e non ho trovato un modo per resettarlo (data-seek non esiste a quanto pare)
do {
	$entryTitle = $row["label"];
	$entryUrl = $row["e"];
	$entryUpdated = $row['modified'];
	$entryId = getIdFromUrl($entryUrl, $entryUpdated);
	$entryContent = trim($entryTitle) . ' - ' .
			'Indirizzo: ' . trim($row['address']) . 
			' - Data: ' . strftime("%A %d %B %Y alle ore %H:%M" , strtotime($row['time']));
	
	$feed->addEntryWithTextContent($entryId, $entryTitle, new DateTime($entryUpdated), $entryContent);
}while( $row = $result->fetch_array() );

/* <entry>
<title><?=$entryTitle?></title>
<id><?=$entryId?></id>
<updated><?=$entryUpdated?></updated>
<summary><?=$entrySummary?></summary>
</entry> */

echo $feed->getFeed();
?>
