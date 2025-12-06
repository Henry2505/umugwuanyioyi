<?php
// src/SupabaseClient.php
namespace App;

use Exception;

class SupabaseClient
{
    private string $supabaseUrl;
    private string $serviceKey;
    private string $anonKey;
    private int $timeout;

    public function __construct(string $supabaseUrl, string $serviceKey = '', string $anonKey = '', int $timeout = 25)
    {
        $this->supabaseUrl = rtrim($supabaseUrl, '/');
        $this->serviceKey  = $serviceKey;
        $this->anonKey     = $anonKey;
        $this->timeout     = $timeout;
        if ($this->supabaseUrl === '') {
            throw new Exception('Supabase URL not configured');
        }
    }

    private function buildHeaders(bool $useServiceKey = true, array $extra = []): array
    {
        $hdr = [];
        if ($useServiceKey && $this->serviceKey) {
            $hdr[] = 'apikey: ' . $this->serviceKey;
            $hdr[] = 'Authorization: Bearer ' . $this->serviceKey;
        } elseif (!$useServiceKey && $this->anonKey) {
            $hdr[] = 'apikey: ' . $this->anonKey;
        } elseif ($this->serviceKey) {
            // fallback
            $hdr[] = 'apikey: ' . $this->serviceKey;
            $hdr[] = 'Authorization: Bearer ' . $this->serviceKey;
        }
        foreach ($extra as $k => $v) $hdr[] = "{$k}: {$v}";
        return $hdr;
    }

    public function rest(string $method, string $path, $body = null, string $query = '', bool $useServiceKey = true, array $extra = [])
    {
        $url = $this->supabaseUrl . $path . ($query ? '?' . $query : '');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        $headers = $this->buildHeaders($useServiceKey, $extra);
        if ($body !== null) {
            $json = is_string($body) ? $body : json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return [$code, $resp, $err];
    }

    public function storagePut(string $bucket, string $path, string $binary, string $contentType = 'application/octet-stream', bool $useServiceKey = true)
    {
        $url = $this->supabaseUrl . "/storage/v1/object/{$bucket}/{$path}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $binary);
        $headers = [
            'Content-Type: ' . $contentType,
        ];
        if ($useServiceKey && $this->serviceKey) {
            $headers[] = 'apikey: ' . $this->serviceKey;
            $headers[] = 'Authorization: Bearer ' . $this->serviceKey;
        } elseif ($this->anonKey) {
            $headers[] = 'apikey: ' . $this->anonKey;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$code, $resp, $err];
    }
}
