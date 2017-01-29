<?php
require_once 'bootstrap.php';

error_reporting(E_ERROR);

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
    'news_web_image'=>function(\stdClass $newsItem){return "$newsItem->news_web_image_uri";},
    'news_easy_image'=>function(\stdClass $newsItem,$baseUrl){return "$baseUrl/$newsItem->news_id/$newsItem->news_easy_image_uri";},
    'news_easy_voice'=>function(\stdClass $newsItem,$baseUrl){return "$baseUrl/$newsItem->news_id/$newsItem->news_easy_voice_uri";}];

function fileDownload($url){
    try{
        $time = microtime();
        $tmpFile = "/tmp/$time";

        $client = new \GuzzleHttp\Client();
        $client->request('GET', $url, ['sink' => $tmpFile ]);


        $data = file_get_contents($tmpFile);
        $base64 = base64_encode($data);

        unlink($tmpFile);
    }catch(\GuzzleHttp\Exception\ClientException $ex){
        $base64 = "";
    }
    
    return $base64;
}

function extractArticle($url,$containerId,$removeTags=['rt','script']){
    try{
        $contents = (new \GuzzleHttp\Client())->request('GET', $url)->getBody()->getContents();
        $doc = DOMDocument::loadHTML($contents);
        $article = $doc->getElementById($containerId);
        foreach($removeTags as $removeTag){
            $list = $article->getElementsByTagName($removeTag);
            while ($list->length > 0) {
                $p = $list->item(0);
                $p->parentNode->removeChild($p);
            }        
        }

        return $article;
    }catch(\GuzzleHttp\Exception\ClientException $ex){
        return null;
    }
}

 $news = (new MongoDB\Client('mongodb://localhost'))->newsdb->news;

$newsArray = \GuzzleHttp\json_decode($content)[0];
foreach ($newsArray as $date=>$newsList){
    echo "$date\n";
    foreach($newsList as $newsItem){
       if($news->count(['news_id'=>$newsItem->news_id])>0){ continue;}
       
       $newsItem->resources = new stdClass();
       
       $newsItem->title = preg_replace("@[ 　]@u","",preg_replace('/\s+/', "", $newsItem->title));
       
       echo "$newsItem->title\n";
       foreach($resources as $key=>$urlExtractor){
           if($newsItem->{"has_$key"}){
               $newsItem->resources->{$key} = fileDownload(call_user_func($urlExtractor,$newsItem,$baseEasy));
           }
       }
       
       if($newsItem->news_web_url != ''){
           $article = extractArticle($newsItem->news_web_url,'main');    
           $newsItem->resources->news_html= $article?preg_replace('/\s+/', " ", $article->ownerDocument->saveHTML($article)):"<p>not found</p>";
       }
       
       $article = extractArticle("$baseEasy/$newsItem->news_id/$newsItem->news_id.html",'newsarticle');
       $newsItem->resources->news_easy_text = $article?preg_replace("@[ 　]@u","",preg_replace('/\s+/', " ", $article->ownerDocument->saveHTML($article))):"<p>not found</p>";
       $newsItem->resources->news_easy_text = preg_replace("@[ 　]@u","",preg_replace('/<ruby>(.*?)<\/ruby>/', '<span>$1</span>', $newsItem->resources->news_easy_text));
       
       $news->insertOne((array)$newsItem);
    }
}

