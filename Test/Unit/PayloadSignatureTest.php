<?php

declare(strict_types=1);

namespace Utrust\Payment\Test\Unit;

use PHPUnit\Framework\TestCase;
use Utrust\Payment\Model\WebhookSignature;

class PayloadSignatureTest extends TestCase
{
    /**
     * Documented joined payload from the xMoney webhook guide.
     */
    const CANONICAL_MESSAGE = 'event_typeORDER.PAYMENT.RECEIVEDresourceamount10.8200'
        . 'resourcecurrencyEURresourcereference1400012634statecompleted';

    /**
     * @return void
     */
    public function testCanonicalMessageMatchesDocumentedPayload(): void
    {
        $signer = new WebhookSignature();

        $this->assertSame(self::CANONICAL_MESSAGE, $signer->canonicalMessage($this->samplePayload()));
    }

    /**
     * A nested field must be part of the signed message, not dropped after one level.
     *
     * @return void
     */
    public function testCanonicalMessageIncludesNestedValues(): void
    {
        $signer = new WebhookSignature();
        $payload = $this->samplePayload();
        $payload['resource']['details'] = ['tax' => '1.00'];

        $message = $signer->canonicalMessage($payload);

        $this->assertStringContainsString('resourcedetailstax1.00', $message);
        $this->assertStringNotContainsString('Array', $message);
    }

    /**
     * @return void
     */
    public function testWrongSignatureDoesNotMatch(): void
    {
        $signer = new WebhookSignature();
        $secret = 'webhook-secret';
        $payload = $this->samplePayload();
        $payload['signature'] = $signer->sign($payload, $secret);

        $tampered = $payload;
        $tampered['resource']['amount'] = '1.00';

        $this->assertTrue(hash_equals($payload['signature'], $signer->sign($payload, $secret)));
        $this->assertFalse(hash_equals($payload['signature'], $signer->sign($tampered, $secret)));
    }

    /**
     * @return array
     */
    private function samplePayload(): array
    {
        return [
            'event_type' => 'ORDER.PAYMENT.RECEIVED',
            'resource' => [
                'reference' => '1400012634',
                'amount' => '10.8200',
                'currency' => 'EUR',
            ],
            'signature' => '5ef8a5994e917c14479b31f690d4d2a023dfcc6059081504e3087977b21580ab',
            'state' => 'completed',
        ];
    }
}
