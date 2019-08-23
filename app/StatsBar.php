<?php

namespace Servidor;

class StatsBar
{
    public static function stats()
    {
        $os = self::parseReleaseFile('os');
        $lsb = self::parseReleaseFile('lsb');

        return [
            'cpu' => self::getCpuUsage(),
            'ram' => self::getRamUsage(),
            'hostname' => gethostname(),
            'os' => [
                'name' => php_uname('s'),
                'distro' => $os['NAME'],
                'version' => $lsb['DISTRIB_RELEASE'],
            ],
        ];
    }

    private static function parseReleaseFile($file)
    {
        $flags = FILE_IGNORE_NEW_LINES;
        $data = [];

        foreach (file('/etc/'.$file.'-release', $flags) as $line) {
            list($key, $val) = explode('=', $line);

            $key = trim($key, '[]');
            $val = trim($val, '"');

            $data[$key] = $val;
        }

        return $data;
    }

    /**
     * Get the current CPU usage in percent.
     */
    private static function getCpuUsage(): float
    {
        return (float) exec("mpstat | tail -n1 | awk '{ print 100 - $12 }'");
    }

    /**
     * Get details about the RAM currently used/free.
     */
    private static function getRamUsage(): array
    {
        $output = exec('free | tail -n+2 | head -n1');

        $data = sscanf($output, '%s %d %d %d %d %d');

        return [
            'total' => round($data[1] / 1024),
            'used' => round($data[2] / 1024),
            'free' => round(($data[3] + $data[5]) / 1024),
        ];
    }
}
