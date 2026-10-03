<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 大喜利掲示板(chinsukoustudy.com)の一覧ページからお題を取得する。
 * 相手サイトへの負荷を抑えるため、取得結果は一定時間キャッシュする。
 */
class OdaiSource
{
    public const PAGE_URL = 'https://chinsukoustudy.com/oogiri-keijiban/';
    public const TOPIC_PREFIX = 'https://chinsukoustudy.com/oogiri-keijiban/forums/odai/';
    public const SITE_NAME = '大喜利掲示板';
    private const CACHE_KEY = 'odai-source.pool';
    private const CACHE_SECONDS = 600;

    /** @return array<int, array{title: string, url: string}> */
    public function pool(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            $response = Http::timeout(10)
                ->withUserAgent('IpponGrandPrix/1.0 (odai picker)')
                ->get(self::PAGE_URL);

            if (! $response->ok()) {
                throw new RuntimeException('お題サイトから取得できませんでした(HTTP '.$response->status().')');
            }

            $pool = $this->parse($response->body());
            if ($pool === []) {
                throw new RuntimeException('お題サイトからお題を読み取れませんでした。ページの構成が変わった可能性があります');
            }

            return $pool;
        });
    }

    /** @return array<int, array{title: string, url: string}> ランダムに選んだ候補 */
    public function candidates(int $count = 3): array
    {
        $pool = $this->pool();
        shuffle($pool);

        return array_slice($pool, 0, $count);
    }

    public function isSourceUrl(string $url): bool
    {
        return str_starts_with($url, self::TOPIC_PREFIX);
    }

    /** @return array<int, array{title: string, url: string}> */
    public function parse(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        // 新着は a.bbp-forum-title、ランキングは a の中の span.views_title にタイトルがある
        $items = [];
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query("//a[contains(@class,'bbp-forum-title')] | //a[.//span[contains(@class,'views_title')]]") as $a) {
            $url = $a->getAttribute('href');
            $titleNode = $xpath->query(".//span[contains(@class,'views_title')]", $a)->item(0);
            $title = trim(($titleNode ?? $a)->textContent);

            if ($title !== '' && $this->isSourceUrl($url)) {
                $items[$url] = ['title' => $title, 'url' => $url];
            }
        }

        return array_values($items);
    }
}
