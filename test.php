<?php
require_once 'bootstrap.php';

$baseUrl = 'http://www3.nhk.or.jp';
$baseEasy = "$baseUrl/news/easy";
$newsUrl = "$baseEasy/news-list.json";

$client = new \GuzzleHttp\Client();

$res = $client->request('GET', $newsUrl);
$content = stripslashes(
        str_replace('﻿', "", 
        str_replace("\"", "\\\"", 
        str_replace("\\", "\\\\", 
        $res->getBody()->getContents()
                ))));

$resources = [
    'news_web_image'=>function(\stdClass $newsItem,$baseUrl){return "$newsItem->news_web_image_uri";},
    'news_easy_image'=>function(\stdClass $newsItem,$baseUrl){return "$baseUrl/$newsItem->news_id/$newsItem->news_easy_image_uri";},
    'news_easy_voice'=>function(\stdClass $newsItem,$baseUrl){return "$baseUrl/$newsItem->news_id/$newsItem->news_easy_voice_uri";}];

function fileDownload($url){
    $time = microtime();
    $tmpFile = "/tmp/$time";
    
    $client = new \GuzzleHttp\Client();
    $client->request('GET', $url, ['sink' => $tmpFile ]);
    
    
    $data = file_get_contents($tmpFile);
    $base64 = base64_encode($data);
    
    unlink($tmpFile);
    
    return $base64;
}

function extractArticle($url,$containerId){
    $contents = (new \GuzzleHttp\Client())->request('GET', $url)->getBody()->getContents();
    $doc = DOMDocument::loadHTML($contents);
    $article = $doc->getElementById($containerId);
    $list = $article->getElementsByTagName("rt");
    while ($list->length > 0) {
        $p = $list->item(0);
        $p->parentNode->removeChild($p);
    }
    return preg_replace('/\s+/', "", $article->textContent);
}

$newsArray = \GuzzleHttp\json_decode($content)[0];
foreach ($newsArray as $date=>$newsList){
    echo "$date\n";
    foreach($newsList as $newsItem){
       echo "$newsItem->title\n";
       foreach($resources as $key=>$urlExtractor){
           if($newsItem->{"has_$key"}){
               $newsItem->{$key} = fileDownload(call_user_func($urlExtractor,$newsItem,$baseEasy));
           }
       }
       
        $newsItem->easy_contents=extractArticle("$baseEasy/$newsItem->news_id/$newsItem->news_id.html",'newsarticle');
        echo print_r($newsItem);
       break;
    }
    break;
}

