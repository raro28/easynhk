<?php

require_once 'bootstrap.php';

//error_reporting(E_ERROR);

$baseUrl = 'http://www3.nhk.or.jp';
$baseEasy = "$baseUrl/news/easy";
$newsUrl = "$baseEasy/news-list.json";

$client = new \GuzzleHttp\Client();

$res = $client->request('GET', $newsUrl);
$content = stripslashes(
        str_replace('﻿', "", str_replace("\"", "\\\"", str_replace("\\", "\\\\", $res->getBody()->getContents()
        ))));

$resources = [
    'news_web_image' => function(\stdClass $newsItem) {
        return "$newsItem->news_web_image_uri";
    },
    'news_easy_image' => function(\stdClass $newsItem, $baseUrl) {
        return "$baseUrl/$newsItem->news_id/$newsItem->news_easy_image_uri";
    },
    'news_easy_voice' => function(\stdClass $newsItem, $baseUrl) {
        return "$baseUrl/$newsItem->news_id/$newsItem->news_easy_voice_uri";
    }];

function cleanSpaces($string, $replacement = "") {
    return preg_replace("@[ 　]@u", $replacement, preg_replace('/\s+/', $replacement, $string));
}

function replaceTag($string, $tag, $replacement) {
    return preg_replace("/<$tag>(.*?)<\/$tag>/", "<$replacement>$1</$replacement>", $string);
}

function fileDownload($url) {
    try {
        $time = microtime();
        $tmpFile = "/tmp/$time";

        $client = new \GuzzleHttp\Client();
        $client->request('GET', $url, ['sink' => $tmpFile]);


        $data = file_get_contents($tmpFile);
        $base64 = base64_encode($data);

        unlink($tmpFile);
    } catch (\GuzzleHttp\Exception\ClientException $ex) {
        $base64 = "";
    }

    return $base64;
}

function extractArticle($url, $containerId, $removeTags = ['rt', 'script']) {
    try {
        $contents = (new \GuzzleHttp\Client())->request('GET', $url)->getBody()->getContents();
        $doc = DOMDocument::loadHTML($contents);
        $article = $doc->getElementById($containerId);
        foreach ($removeTags as $removeTag) {
            $list = $article->getElementsByTagName($removeTag);
            while ($list->length > 0) {
                $p = $list->item(0);
                $p->parentNode->removeChild($p);
            }
        }

        return $article;
    } catch (\GuzzleHttp\Exception\ClientException $ex) {
        return null;
    }
}

function resampleAudio($audioContents) {
    $timeStamp = time();
    $inputFile = "/dev/shm/in.$timeStamp.mp3";
    $outputFile = "/dev/shm/out.$timeStamp.mp3";

    file_put_contents($inputFile, base64_decode($audioContents));

    $output = "";
    $return_var = -1;
    exec("lame -V5 --quiet --vbr-new --resample 44.1 $inputFile $outputFile", $output, $return_var);

    if (intval($output) != 0) {
        diel('cant convert');
    }

    return base64_encode(file_get_contents($outputFile));
}

$news = (new MongoDB\Client('mongodb://localhost'))->newsdb->news;

$newsArray = \GuzzleHttp\json_decode($content)[0];
foreach ($newsArray as $date => $newsList) {
    echo "$date\n";
    foreach ($newsList as $newsItem) {
        if ($news->count(['news_id' => $newsItem->news_id]) > 0) {
            continue;
        }

        $newsItem->resources = new stdClass();

        $newsItem->title = cleanSpaces($newsItem->title);

        echo "$newsItem->title\n";
        foreach ($resources as $key => $urlExtractor) {
            if ($newsItem->{"has_$key"}) {
                $contents = fileDownload(call_user_func($urlExtractor, $newsItem, $baseEasy));
                $newsItem->resources->{$key} = $key == 'news_easy_voice' ? resampleAudio($contents) : $contents;
            }
        }

        if ($newsItem->news_web_url != '') {
            $article = extractArticle($newsItem->news_web_url, 'main');
            $newsItem->resources->news_html = $article ? cleanSpaces($article->ownerDocument->saveHTML($article)) : "''";
        }

        $article = extractArticle("$baseEasy/$newsItem->news_id/$newsItem->news_id.html", 'newsarticle');
        $newsItem->resources->news_easy_text = $article ? cleanSpaces($article->ownerDocument->saveHTML($article), ' ') : "''";
        $newsItem->resources->news_easy_text = cleanSpaces(replaceTag($newsItem->resources->news_easy_text, 'ruby', 'span'), ' ');

        $news->insertOne((array) $newsItem);
    }
}

