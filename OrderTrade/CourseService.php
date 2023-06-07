<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Redis;

class CourseService
{
    private const USDT_COURSES = 'usdt_course';

    public static function add(string $ticker, array $data)
    {
        self::saveCourses($ticker, $data);
    }

    public static function getCourses(): array
    {
        $courses = [];
        foreach (self::getKeys() as $key) {
            $json = Redis::get($key);
            if (!$json) {
                Redis::del($key);
            } else {
                $courses[] = json_decode($json, true);
            }
        }
        return $courses;
    }

    protected static function saveCourses(string $ticker, array $data): void
    {
        Redis::set(self::USDT_COURSES . ':' . $ticker, json_encode($data));
    }

    public static function clear(): void
    {
        Redis::del(self::getKeys());
    }

    private static function getKeys()
    {
        return Redis::keys(self::USDT_COURSES . ':*');
    }
}
