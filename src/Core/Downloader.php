<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Core;

use danielsreichenbach\GeoIP2Update\Exception\DownloadException;
use danielsreichenbach\GeoIP2Update\Model\Config;

class Downloader
{
    private const BASE_URL = 'https://download.maxmind.com';
    private const USER_AGENT = 'geoip2-update/3.0';
    private ?\Closure $progressCallback = null;

    public function __construct(
        private FileManager $fileManager,
    ) {
    }

    public function setProgressCallback(?\Closure $callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * @return array{edition_id: string, date: string}|null
     */
    public function getRemoteInfo(Config $config, string $edition): ?array
    {
        $url = sprintf(
            '%s/geoip/databases/%s/update',
            self::BASE_URL,
            $edition
        );

        $ch = curl_init($url);

        if (!$ch) {
            throw new DownloadException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => sprintf('%s:%s', $config->getAccountId(), $config->getLicenseKey()),
            CURLOPT_USERAGENT => self::USER_AGENT,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (200 !== $httpCode || !is_string($response)) {
            return null;
        }

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            return [
                'edition_id' => $edition,
                'date' => $data['date'] ?? date('Y-m-d'),
            ];
        } catch (\JsonException $e) {
            return null;
        }
    }

    /**
     * Download database archive.
     *
     * @param array{edition_id: string, date: string} $remoteInfo
     */
    public function download(Config $config, string $edition, array $remoteInfo): ?string
    {
        $url = sprintf(
            '%s/geoip/databases/%s/download?%s',
            self::BASE_URL,
            $edition,
            http_build_query([
                'date' => (date_create($remoteInfo['date']) ?: new \DateTime())->format('Ymd'),
                'suffix' => 'tar.gz',
            ])
        );

        $tempFile = $this->fileManager->getTempFile($edition . '.tar.gz');

        $ch = curl_init($url);
        $fh = fopen($tempFile, 'wb');

        if (!$ch || !$fh) {
            throw new DownloadException('Failed to initialize download');
        }

        $curlOptions = [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => sprintf('%s:%s', $config->getAccountId(), $config->getLicenseKey()),
            CURLOPT_USERAGENT => self::USER_AGENT,
        ];

        // Add progress callback if available
        if (null !== $this->progressCallback) {
            $curlOptions[CURLOPT_NOPROGRESS] = false;
            $curlOptions[CURLOPT_PROGRESSFUNCTION] = function ($resource, $downloadSize, $downloaded) {
                if ($downloadSize > 0) {
                    call_user_func($this->progressCallback, $downloadSize, $downloaded);
                }

                return 0;
            };
        }

        curl_setopt_array($ch, $curlOptions);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if (!$success || 200 !== $httpCode) {
            $this->fileManager->deleteFile($tempFile);

            return null;
        }

        return $tempFile;
    }
}
