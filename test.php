<?php
require_once 'bootstrap.php';

$baseUrl = 'http://www3.nhk.or.jp';
$newsUrl = "$baseUrl/news/easy/news-list.json";

$client = new \GuzzleHttp\Client();
$res = $client->request('GET', $newsUrl);
$content = stripslashes(
        str_replace('﻿', "", 
        str_replace("\"", "\\\"", 
        str_replace("\\", "\\\\", 
        $res->getBody()->getContents()
                ))));

/*
 {
  "news_priority_number": "1",
  "news_prearranged_time": "2017-01-20 16:00:00",
  "news_id": "k10010845961000",
  "title": "文部科学省が法律に違反して職員の次の仕事を探した",
  "title_with_ruby": "<ruby>文部科学省<rt>もんぶかがくしょう</rt></ruby>が<ruby>法律<rt>ほうりつ</rt></ruby>に<ruby>違反<rt>いはん</rt></ruby>して<ruby>職員<rt>しょくいん</rt></ruby>の<ruby>次<rt>つぎ</rt></ruby>の<ruby>仕事<rt>しごと</rt></ruby>を<ruby>探<rt>さが</rt></ruby>した",
  "news_file_ver": false,
  "news_creation_time": "2017-01-20 16:20:15",
  "news_preview_time": "2017-01-20 16:20:15",
  "news_publication_time": "2017-01-20 15:42:34",
  "news_publication_status": true,
  "has_news_web_image": true,
  "has_news_web_movie": true,
  "has_news_easy_image": false,
  "has_news_easy_movie": false,
  "has_news_easy_voice": true,
  "news_web_image_uri": "http://www3.nhk.or.jp/news/html/20170120/../20170120/K10010845961_1701200439_1701200440_01_03.jpg",
  "news_web_movie_uri": "k10010845961_201701200439_201701200440.mp4",
  "news_easy_image_uri": "''",
  "news_easy_movie_uri": "''",
  "news_easy_voice_uri": "k10010845961000.mp3",
  "news_display_flag": true,
  "news_web_url": "http://www3.nhk.or.jp/news/html/20170120/k10010845961000.html"
}
 */
$newsArray = \GuzzleHttp\json_decode($content);
foreach ($newsArray[0] as $date=>$newsList){
    echo "$date\n";
    foreach($newsList as $newsItem){
       echo "$newsItem->title\n";
    }
    echo "\n";
}