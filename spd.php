<?php

// Check how many parameters are passed
// first is always skript name
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

switch (empty($argc)) {
    case true:

        if (!isset($_POST["username"]) || !isset($_POST["password"])) {
            exit("username requried\n");
        }
        if (!isset($_POST["username"])) {
            exit("password requried\n");
        }
        if (!isset($_POST["type"])) {
            exit("type requried\n");
        } else {
            if ($_POST["type"] !== "conviction" && $_POST["type"] !== "spd-stronghold") {
                exit("invalid index type\n");
            }
        }
        if (!isset($_POST["wk_key"])) {
            exit("whalkreis key requried\n");
        }

        $user = strval($_POST["username"]);
        $password = strval($_POST["password"]);
        $type = strval($_POST["type"]);
        $wkKey = strval($_POST["wk_key"]);
        break;
    case false:

        if ($argv < 5) {
            exit("you need to pass at least 4 parameters: username, password, type and wk_key\n");
        }
        if ($argv[3] !== "conviction" && $argv[3] !== "spd-stronghold") {
            exit("invalid index type\n");
        }

        $user = strval($argv[1]);
        $password = strval($argv[2]);
        $type = strval($argv[3]);
        $wkKey = strval($argv[4]);
        break;
}

require 'vendor/autoload.php';

// Suppress libxml html5 warnings
libxml_use_internal_errors(true);

$client = new GuzzleHttp\Client([
    'cookies'         => true,
    'allow_redirects' => [
        'track_redirects' => true,
        'max'             => 10,        // allow at most 10 redirects.
        'protocols'       => ['https'],
    ]
]);

// Get Auth URL
$res = $client->request('GET', 'https://planer.spd.de/planer/');

// Parse HTML to get AuthState field
$content = $res->getBody()->getContents();
$dom = new DOMDocument();
$dom->loadHTML($content);
$xp = new DOMXpath($dom);
$nodes = $xp->query('//input[@name="AuthState"]');
$node = $nodes->item(0);
$authState = $node->getAttribute('value');

$redirects = $res->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);

// Authenticate on final redirect
$res = $client->request('POST', end($redirects), [
    'form_params' => [
        'username'  => $user,
        'password'  => $password,
        'AuthState' => $authState
    ]
]);

// Prepare secondary auth no clue what actually happens here
$dom->loadHTML($res->getBody()->getContents());
$xp = new DOMXpath($dom);

$nodes = $xp->query('//form');
$node = $nodes->item(0);
$actionUrl = $node->getAttribute('action');

$nodes = $xp->query('//input[@name="SAMLResponse"]');
$node = $nodes->item(0);
$samlResponse = $node->getAttribute('value');

$nodes = $xp->query('//input[@name="RelayState"]');
$node = $nodes->item(0);
$relayState = $node->getAttribute('value');

$res = $client->request('POST', $actionUrl, [
    'form_params' => [
        'SAMLResponse' => $samlResponse,
        'RelayState'   => $relayState
    ]
]);

// Finally get "wahlkreis" data
$res = $client->request('POST', 'https://planer.spd.de/planer/ajax_custom_planer', [
    'form_params' => [
        'type'                  => "wahlkreis",
        'key'                   => $wkKey,
        "level"                 => "childs",
        "wahl[wahlinstitution]" => "Bundestag",
        "wahl[wahljahr]"        => "2021",
        "wahl[version]"         => "0619",
        "wahl[caption]"         => "Bundestag 2021",
        "mobIndex[scope]"       => "wkr",
        "mobIndex[indexart]"    => $type,
        "hierarchie"            => "wkr",
        "skipOverKgs8"          => "true",
        "ajax"                  => "getShapes"
    ]
]);

// Validation
if (empty($res->getBody())) {
    exit("Invalid data recieved. Possible Authentication Failure\n");
}
$json = json_decode($res->getBody());
if (empty($json->data->geoJson->features)) {
    exit("No features recieved. Check if your parameters are correct\n");
}

$file = fopen("structuredData_{$type}_wk_{$wkKey}.csv", 'w');

// Get "zielgruppen" names as headings
$zielgruppen = array_keys((array)$json->data->geoJson->features[0]->properties->zielgruppen);

// Set Headings merges with zielgruppen
fputcsv($file, array_merge(array('Gemeinde', 'Schlüssel', 'MobIndex'), $zielgruppen));

// Iterate features
foreach ($json->data->geoJson->features as $feature) {

    // Make "city" more accurate
    $gemeinde = str_replace("Gemeinde: ", "", $feature->properties->infos[0]);
    $gemeinde = str_replace(", Stadt", "", $gemeinde);

    // Get "zielgruppen" indices
    $zielgruppen = array_values((array)$feature->properties->zielgruppen);

    // save the column headers
    fputcsv($file, array_merge(array($gemeinde, $feature->properties->key, $feature->mob_class), $zielgruppen));
}

// Close the file
fclose($file);