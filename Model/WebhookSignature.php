<?php

declare(strict_types=1);

namespace Utrust\Payment\Model;

class WebhookSignature
{
    /**
     * Canonical string xMoney signs, with the signature field removed.
     *
     * @param array $payload
     * @return string
     */
    public function canonicalMessage(array $payload): string
    {
        unset($payload['signature']);
        $flat = $this->flatten($payload);
        ksort($flat);
        $message = '';
        foreach ($flat as $key => $value) {
            $message .= $key . $this->stringify($value);
        }

        return $message;
    }

    /**
     * @param array $payload
     * @param string $secret
     * @return string
     */
    public function sign(array $payload, string $secret): string
    {
        return hash_hmac('sha256', $this->canonicalMessage($payload), $secret);
    }

    /**
     * @param array $array
     * @param string $prefix
     * @return array
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $compoundKey = $prefix . $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $compoundKey);
                continue;
            }
            $result[$compoundKey] = $value;
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function stringify($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
